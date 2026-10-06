<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev\Command;

use BDBlockDisplay\BobyDev\Core\BlockRegistry;
use BDBlockDisplay\BobyDev\Display\BlockDisplayManager;
use BDBlockDisplay\BobyDev\Entity\DisplayEntity;
use BDBlockDisplay\BobyDev\Entity\ModelFaceEntity;
use BDBlockDisplay\BobyDev\Service\ToolService;
use BDBlockDisplay\BobyDev\UI\EditorUI;
use BDBlockDisplay\BobyDev\main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use const BDBlockDisplay\BobyDev\BD_VERSION;

final class BDBlockCommand extends Command{
    public function __construct(
        private main $plugin,
        private BlockDisplayManager $manager,
        private EditorUI $ui,
        private ToolService $tools,
    ){
        parent::__construct('bdbd','BDBlockDisplay editor','/bdbd <subcommand>');
        $this->setPermission('bdbd.use');
    }

    public function execute(CommandSender $sender,string $label,array $args):bool{
        if(!$sender instanceof Player){
            $sender->sendMessage('Use this command in-game.');
            return true;
        }
        $this->handle($sender,$args);
        return true;
    }

        private function handle(Player $p,array $args):void{
            $sub=strtolower($args[0]??'help');switch($sub){case 'wand':$this->tools->giveTools($p);break;case 'tools':$this->tools->giveTools($p);break;case 'menu':$d=$this->manager->selected($p)??$this->manager->findInSight($p,[DisplayEntity::class]);if($d instanceof DisplayEntity)$this->ui->openMenu($p,$d);else$p->sendMessage('§cNo display selected.');break;case 'spawn':$id=$args[1]??'stone';$pos=$p->getPosition()->addVector($p->getDirectionVector()->multiply(3));$d=new DisplayEntity(new Location($pos->x,$pos->y,$pos->z,$p->getWorld(),$p->getLocation()->yaw,$p->getLocation()->pitch),null);if(!$d->setBlockId($id)){$d->flagForDespawn();$p->sendMessage('§cUnknown block.');break;}$d->setMode(1);$d->spawnToAll();$this->manager->syncModel($d);$this->manager->select($p,$d);break;case 'control':$d=$this->manager->selected($p)??$this->manager->findInSight($p,[DisplayEntity::class]);if($d instanceof DisplayEntity){if($this->manager->hasPlayerControl($p))$this->manager->stopPlayerControl($p,true);else $this->manager->startPlayerControl($p,$d);}else$p->sendMessage('§cNo display selected.');break;case 'group':$this->ui->openGroupMenu($p);break;case 'grouptool':$this->ui->openGroupMenu($p);break;case 'mode':$m=strtolower($args[1]??'');$map=['off'=>0,'move'=>1,'rotate'=>2,'scale'=>3,'calibrate'=>4];$d=$this->manager->selected($p);if($d instanceof DisplayEntity&&isset($map[$m])){$this->manager->stopDrag($p);$this->manager->setMode($d,$map[$m]);$this->manager->refreshHandles($p);}else$p->sendMessage('§7Usage: /bdbd mode <off|move|rotate|scale|calibrate>');break;case 'drag':$action=strtolower($args[1]??'');if($action==='stop'){$this->manager->stopDrag($p);$p->sendMessage('§7Drag stopped.');}else{$p->sendMessage('§7Usage: /bdbd drag stop');}break;case 'solid':$d=$this->manager->selected($p);if($d instanceof DisplayEntity){$v=strtolower($args[1]??'toggle');$d->setSolid($v==='on'?true:($v==='off'?false:!$d->isSolid()));$this->manager->refreshClientSolidCollision($p);$p->sendMessage('§eWalkable / Solid: '.($d->isSolid()?'ON':'OFF').' §7(client-native collision, no world blocks)');}break;case 'delete':$d=$this->manager->selected($p);if($d instanceof DisplayEntity){$this->manager->deleteDisplay($d);$this->manager->clearSelection($p);}$p->sendMessage('§aDeleted selected display.');break;case 'cleanup':$this->manager->clearAllHandles();$n=0;foreach($this->plugin->getServer()->getWorldManager()->getWorlds() as $w)foreach($w->getEntities() as $e)if($e instanceof ModelFaceEntity && $e->isClosed()===false && $this->manager->findOwnerDisplay($e)===null){$e->flagForDespawn();$n++;}$p->sendMessage('§aCleanup complete: '.$n.' model entities.');break;case 'list':$list=$this->manager->listDisplays($p);$p->sendMessage('§eDisplays in world: '.count($list));foreach(array_slice($list,0,20) as $d)$p->sendMessage('§7#'.$d->getId().' '.$d->getBlockId().' size='.$d->getDisplaySize());break;case 'version':$p->sendMessage('§aBDBlockDisplay '.BD_VERSION.' — converted from BlockDisplay v0.5.1');break;case 'debug':$p->sendMessage('§7Selected='.($this->manager->selected($p)?->getId()??'none').' Control='.($this->manager->hasPlayerControl($p)?'ON':'OFF').' Drag='.($this->manager->hasActiveDrag($p)?'ON':'OFF'));break;default:$p->sendMessage('§eBDBlockDisplay: /bdbd wand | spawn <block> | menu | control | group | grouptool | mode <move|rotate|scale|calibrate> | drag stop | solid [on|off|toggle] | delete | cleanup | list | version | debug');}}
}
