<?php

namespace App\Console\Commands;

use App\Services\RuleImporter;
use Illuminate\Console\Command;

class ImportRulebook extends Command
{
    protected $signature = 'reglamento:importar {carpeta : Carpeta con rules.json, appendices.json y glossary.json (salida de ingest/ingest.php)} {--edicion= : Nombre de la edición}';

    protected $description = 'Carga el reglamento IPSC (en inglés) en la base de datos y reconstruye el índice de búsqueda';

    public function handle(RuleImporter $importer): int
    {
        $dir = rtrim($this->argument('carpeta'), '/\\');
        $data = [];
        foreach (['rules', 'appendices', 'glossary'] as $name) {
            $file = "$dir/$name.json";
            if (! is_file($file)) {
                $this->error("Falta $file");

                return self::FAILURE;
            }
            $data[$name] = json_decode(file_get_contents($file), true);
            if (! is_array($data[$name])) {
                $this->error("$file no es un JSON válido");

                return self::FAILURE;
            }
        }

        $edition = $this->option('edicion') ?: config('ipscrules.edition');
        $c = $importer->import($edition, $data['rules'], $data['appendices'], $data['glossary']);

        $this->info("Importado «{$edition}»: {$c['rules']} reglas, {$c['variants']} variantes, {$c['glossary']} términos, {$c['appendices']} apéndices, {$c['indexed']} filas indexadas.");

        return self::SUCCESS;
    }
}
