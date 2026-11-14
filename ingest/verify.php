<?php
$rules = json_decode(file_get_contents($argv[1] . '/rules.json'), true);
$by = []; foreach ($rules as $r) $by[$r['number']] = $r;
// 1. Índice del PDF vs reglas
$toc = [];
foreach (preg_split('/\R/', file_get_contents($argv[2])) as $i => $l) {
    if ($i > 400) break;
    if (preg_match('/^(\d{1,2}(?:\.\d{1,3})+)\s+.+?\.{5,}/u', $l, $m)) $toc[$m[1]] = true;
}
$missing = array_diff_key($toc, $by);
$extra = array_filter(array_keys($by), fn($n) => $by[$n]['depth'] <= 3 && !isset($toc[$n]));
echo "TOC: ".count($toc)." entradas; sin regla en la ingesta: ".count($missing)." ".implode(',', array_slice(array_keys($missing),0,30))."\n";
echo "Reglas nivel<=3 que no están en el TOC: ".count($extra)." ".implode(',', array_slice($extra,0,30))."\n\n";
// 2. Casos
function applic(array $r, string $d): string { $o=[]; foreach($r['variants'] as $v) if(in_array($d,$v['disciplines'])) $o[]=$v['text']; return implode(' || ', $o); }
foreach (['10.4.2'=>'S','10.4.2 '=>'H','8.1.1.1'=>'H','8.1.1.1 '=>'R','1.2.1.1'=>'S','10.3.2'=>'H','5.6.1.1'=>'M'] as $n=>$d) {
  $n=trim($n); echo "== $n [$d]: ".mb_substr(applic($by[$n], $d),0,260)."\n";
}
echo "\nVariantes vacías: ".count(array_filter($rules, fn($r)=>array_filter($r['variants'], fn($v)=>$v['text']==='')))."\n";
echo "Reglas profundidad>=3 sin texto: ".count(array_filter($rules, fn($r)=>$r['depth']>=3 && !array_filter($r['variants'], fn($v)=>$v['text']!=='')))."\n";
$long = array_filter($rules, fn($r)=>strlen(implode('',array_column($r['variants'],'text')))>3000);
echo "Reglas > 3000 caracteres: ".implode(',', array_column($long,'number'))."\n";
