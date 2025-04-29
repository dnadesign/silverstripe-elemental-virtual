<?php

namespace DNADesign\ElementalVirtual\Tasks;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class ResetGloballyEnabledElements extends BuildTask
{
    protected static string $commandName = 'elemental:virtual:reset-globally-enabled';

    protected string $title = 'Reset Globally Enabled elements';

    protected static string $description = "Reset individual elements 'AvailableGlobally' setting via the YAML config";

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        // get all classes of BaseElement
        $elementClasses = ClassInfo::subclassesFor(BaseElement::class);
        $default = Config::inst()->get(BaseElement::class, 'default_global_elements') ? 1 : 0;

        // first update all to the default
        DB::query("UPDATE Element SET AvailableGlobally = $default");
        DB::query("UPDATE Element_Live SET AvailableGlobally = $default");
        DB::query("UPDATE Element_Versions SET AvailableGlobally = $default");

        foreach ($elementClasses as $class) {
            $isGlobal = Config::inst()->get($class, 'default_global_elements') ? 1 : 0;
            $ids = $class::get()->getIDList();
            if (!empty($ids)) {
                $idStr = implode("','", $ids);
                DB::query("UPDATE Element SET AvailableGlobally = $isGlobal WHERE ID IN ('$idStr')");
                DB::query("UPDATE Element_Live SET AvailableGlobally = $isGlobal WHERE ID IN ('$idStr')");
                DB::query("UPDATE Element_Versions SET AvailableGlobally = $isGlobal WHERE RecordID IN ('$idStr')");
            }
        }

        return Command::SUCCESS;
    }
}
