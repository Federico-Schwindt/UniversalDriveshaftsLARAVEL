<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AssistantBudget;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Owner, 2026-11-14: "un tope de gasto diario para el asistente… máximo $30 en api y unos 10 mensajes por día por customer". */
class AssistantBudgetTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
        DB::table('assistant_spend')->delete();
    }

    private function customer(): User
    {
        $u = User::query()->create(['name' => 'Budget Test', 'email' => 'budget-' . uniqid() . '@example.test', 'password' => bcrypt('x' . uniqid())]);
        DB::table('users')->where('id', $u->id)->update(['email_verified_at' => now(), 'created_at' => now()->subDay()]);
        return $u->fresh();
    }

    public function test_answers_are_priced_by_model_tokens_and_searches(): void
    {
        $u = ['inputTokens' => 1_000_000, 'outputTokens' => 100_000, 'cacheReadInputTokens' => 2_000_000, 'cacheCreationInputTokens' => 0];
        $this->assertEqualsWithDelta(4 + 2 + 0.4, AssistantBudget::cost('claude-opus-5-5', $u), 1e-9);
        $this->assertEqualsWithDelta(2 + 1 + 0.4, AssistantBudget::cost('claude-sonnet-5-5', $u), 1e-9);
        $this->assertEqualsWithDelta(1.25 * 4, AssistantBudget::cost('claude-opus-5-5', ['cacheCreationInputTokens' => 1_000_000]), 1e-9);
        $this->assertEqualsWithDelta(0.03, AssistantBudget::cost('claude-sonnet-5-5', (object) ['serverToolUse' => (object) ['webSearchRequests' => 3]]), 1e-9);
        $this->assertEqualsWithDelta(10 + 5, AssistantBudget::cost('some-new-model', ['inputTokens' => 1_000_000, 'outputTokens' => 100_000]), 1e-9, 'unknown models price as the dearest');
    }

    public function test_the_bot_stops_for_the_day_at_the_budget_and_tells_the_shop_once(): void
    {
        Settings::set('assistant.daily_budget', 30);
        AssistantBudget::record('claude-opus-5-5', ['inputTokens' => 5_000_000, 'outputTokens' => 400_000]);   // $28
        $this->assertFalse(AssistantBudget::exhausted());
        $this->actingAs($this->customer());
        AssistantBudget::record('claude-opus-5-5', ['outputTokens' => 150_000]);   // +$3 = $31
        $this->assertTrue(AssistantBudget::exhausted());
        $this->assertEqualsWithDelta(31.0, AssistantBudget::spentToday(), 1e-6);
        AssistantBudget::record('claude-opus-5-5', ['outputTokens' => 1000]);
        Mail::assertSent(\App\Mail\Branded::class, 1);

        $this->postJson('/assistant', ['message' => 'how much is 5-153X?'])->assertStatus(429)->assertJsonPath('error', fn ($e) => str_contains($e, 'Talk to a person'));
        $this->assertSame(0, DB::table('chat_messages')->where('display', 'how much is 5-153X?')->count(), 'nothing reached the API');
    }

    public function test_ten_questions_a_day_per_customer(): void
    {
        $u = $this->customer();
        $this->actingAs($u);
        $conv = app(\App\Services\Chatbot\ChatbotService::class)->conversation($u);
        for ($i = 0; $i < 10; $i++) DB::table('chat_messages')->insert(['conversation_id' => $conv->id, 'role' => 'user', 'content' => '[]', 'display' => "question $i", 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/assistant', ['message' => 'one more'])->assertStatus(429)->assertJsonPath('error', fn ($e) => str_contains($e, "today's limit"));
    }
}
