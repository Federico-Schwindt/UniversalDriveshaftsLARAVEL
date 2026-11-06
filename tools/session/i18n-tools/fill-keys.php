<?php
// Hand translations for keys i18n:translate cannot place (HTML notes, @mail: request types), faithful to the English.
$a = '<a href="/resources/one-piece-driveshaft" target="_blank">';
$fill = [
  'tools' => [
    'fr' => [
      '__noteA' => "<strong>Véhicules modifiés :</strong> cet arbre de transmission est conçu pour les véhicules d’origine ; vérifiez la longueur de votre arbre de transmission si votre camion est surélevé, mis à niveau ou modifié. Nous pouvons peut-être fabriquer un arbre de transmission sur mesure adapté aux caractéristiques de votre véhicule : contactez-nous dès aujourd’hui en indiquant la longueur de votre arbre de transmission ({$a}guide de mesure</a>). La longueur maximale d’arbre de transmission que UDS peut fabriquer est de 89 pouces, du joint d’étanchéité au centre du croisillon.",
      '__noteAB' => "Nous vous invitons à passer votre commande dès aujourd’hui ; nous vous recontacterons ensuite pour recueillir les informations essentielles. Vous pouvez aussi accélérer le traitement en indiquant vos mesures ci-dessous. Mesurez du joint d’étanchéité de la boîte de transfert jusqu’à la face plane du différentiel, où est montée la bride à 4 boulons de l’arbre de transmission. Votre commande d’arbre de transmission reste en attente tant que nous n’avons pas reçu ces informations indispensables.",
    ],
    'zh' => [
      '__noteA' => "<strong>改装车辆：</strong>本传动轴适用于原厂车辆；如果您的卡车经过升高、调平或其他改装，请再次确认传动轴长度。我们或许可以按照您车辆的具体规格为您定制传动轴——请立即联系我们并提供您的传动轴长度（{$a}测量指南</a>）。UDS 可制造的传动轴最大长度为 89 英寸（从油封到万向节中心）。",
    ],
    'ja' => [
      '__noteA' => "<strong>改造車両:</strong> このプロペラシャフトは純正車両向けに設計されています。リフトアップ、レベリング、その他の改造を行ったトラックの場合は、プロペラシャフトの長さを再度ご確認ください。お客様の車両仕様に合わせたカスタムプロペラシャフトを製作できる場合があります。プロペラシャフトの長さを添えて、今すぐお問い合わせください（{$a}測定ガイド</a>）。UDS で製作できるプロペラシャフトの最大長は、シールからユニバーサルジョイント中心まで 89 インチです。",
    ],
  ],
  'emails' => [
    'zh' => ['@mail:Cancel' => '取消', '@mail:Exchange' => '换货', '@mail:Return' => '退货'],
    'ja' => ['@mail:Cancel' => 'キャンセル', '@mail:Exchange' => '交換', '@mail:Return' => '返品'],
  ],
  'catalog' => [
    'zh' => ['Part number: A to Z' => '零件号：A 到 Z', 'Part number: Z to A' => '零件号：Z 到 A'],
  ],
];
foreach ($fill as $area => $langs) foreach ($langs as $l => $pairs) {
  $p = "lang/i18n/$area/$l.json";
  $j = json_decode(file_get_contents($p), true);
  foreach ($pairs as $k => $v) $j[$k] = $v;
  file_put_contents($p, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
  echo "$p +" . count($pairs) . "\n";
}
