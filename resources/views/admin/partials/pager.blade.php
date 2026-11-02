@if($rows->hasPages() || $rows->total() > 0)
<div class="pager">
  <span>{{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ number_format($rows->total()) }}</span>
  @if($rows->hasPages())
  <div class="pager__links">
    <a href="{{ $rows->previousPageUrl() ?: '#' }}" class=" @if($rows->onFirstPage()) dis @endif" aria-label="Previous">‹</a>
    @foreach($rows->getUrlRange(max(1,$rows->currentPage()-2), min($rows->lastPage(),$rows->currentPage()+2)) as $pg=>$url)
      <a href="{{ $url }}" class=" @if($pg===$rows->currentPage()) is-on @endif">{{ $pg }}</a>
    @endforeach
    <a href="{{ $rows->nextPageUrl() ?: '#' }}" class=" @if(!$rows->hasMorePages()) dis @endif" aria-label="Next">›</a>
  </div>
  @endif
</div>
@endif
