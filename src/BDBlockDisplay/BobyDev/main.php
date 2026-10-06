<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev;

use BDBlockDisplay\BobyDev\Command\BDBlockCommand;
use BDBlockDisplay\BobyDev\Display\BlockDisplayManager;
use BDBlockDisplay\BobyDev\Entity\DisplayEntity;
use BDBlockDisplay\BobyDev\Listener\EditorListener;
use BDBlockDisplay\BobyDev\Service\RegistrationService;
use BDBlockDisplay\BobyDev\Service\ToolService;
use BDBlockDisplay\BobyDev\UI\EditorUI;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
const BD_VERSION='1.0.3';

final class main extends PluginBase{
    private BlockDisplayManager $manager;
    private ToolService $tools;
    private EditorUI $ui;

    protected function onEnable():void{
        $this->manager=new BlockDisplayManager($this);
        $this->tools=new ToolService($this->manager);
        $this->ui=new EditorUI($this->manager);
        (new RegistrationService($this))->registerAll();
        $this->getServer()->getPluginManager()->registerEvents(
            new EditorListener($this->manager,$this->tools,$this->ui),
            $this
        );
        $this->getServer()->getCommandMap()->register(
            'bdbd',
            new BDBlockCommand($this,$this->manager,$this->ui,$this->tools)
        );
        $this->scheduleTasks();
        $this->getLogger()->info(
            'BDBlockDisplay '.BD_VERSION.' enabled. Embedded Customies-compatible registration + internal form layer active (virtual walkable collision).'
        );
    }

    private function scheduleTasks():void{
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function():void{
            foreach($this->getServer()->getOnlinePlayers() as $player){
                $this->manager->refreshClientSolidCollision($player);
            }
        }),1);
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function():void{
            foreach($this->getServer()->getWorldManager()->getWorlds() as $world){
                foreach($world->getEntities() as $entity){
                    if($entity instanceof DisplayEntity && !$entity->isClosed()){
                        $this->manager->syncModel($entity);
                    }
                }
            }
        }),40);
    }
}
