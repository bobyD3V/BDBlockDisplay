<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev\Listener;

use BDBlockDisplay\BobyDev\Display\BlockDisplayManager;
use BDBlockDisplay\BobyDev\Entity\DisplayEntity;
use BDBlockDisplay\BobyDev\Entity\HandleEntity;
use BDBlockDisplay\BobyDev\Service\ToolService;
use BDBlockDisplay\BobyDev\UI\EditorUI;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;

final class EditorListener implements Listener{
    public function __construct(
        private BlockDisplayManager $manager,
        private ToolService $tools,
        private EditorUI $ui,
    ){}

        public function onItemUse(PlayerItemUseEvent $ev):void{
            $k=$this->tools->itemKind($ev->getItem());if($k===null)return;
            $ev->cancel();$p=$ev->getPlayer();
            if($this->manager->hasPlayerControl($p)){
                $d=$this->manager->getPlayerControlDisplay($p);
                $this->manager->stopPlayerControl($p,true);
                if($d!==null)$this->ui->openDisplayMenu($p,$d);
                return;
            }
            $target=$this->manager->findInSight($p,[DisplayEntity::class,HandleEntity::class]);
            if($target instanceof HandleEntity){$this->manager->applyHandle($p,$target);return;}
            if($target instanceof DisplayEntity){
                $this->manager->select($p,$target);
                if($k==='group'){$this->manager->toggleGroupMember($p,$target);return;}
                if($k==='rotate'){$this->manager->setMode($target,2);$this->manager->refreshHandles($p);$p->sendMessage('§eRotate tool active.');return;}
                if($k==='scale'){$this->manager->setMode($target,3);$this->manager->refreshHandles($p);$p->sendMessage('§dScale tool active.');return;}
                $this->ui->openDisplayMenu($p,$target);return;
            }
            if($k==='group'){$this->ui->openGroupMenu($p);return;}
            if($k==='wand'){$this->tools->spawnFromHeld($p);return;}
            $p->sendMessage('§7No display in sight.');
        }
        public function onEntityInteract(PlayerEntityInteractEvent $ev):void{
            $e=$ev->getEntity();if(!$e instanceof DisplayEntity && !$e instanceof HandleEntity)return;
            $ev->cancel();$p=$ev->getPlayer();$kind=$this->tools->itemKind($p->getInventory()->getItemInHand());
            if($e instanceof HandleEntity){$this->manager->startDrag($p,$e);return;}
            if($kind==='group'){$this->manager->toggleGroupMember($p,$e);return;}
            if($kind==='rotate'){$this->manager->stopDrag($p);$this->manager->select($p,$e);$this->manager->setMode($e,2);$this->manager->refreshHandles($p);$p->sendMessage('§eRotate tool active.');return;}
            if($kind==='scale'){$this->manager->stopDrag($p);$this->manager->select($p,$e);$this->manager->setMode($e,3);$this->manager->refreshHandles($p);$p->sendMessage('§dScale tool active.');return;}
            if($this->manager->hasPlayerControl($p)&&$this->manager->getPlayerControlDisplay($p)?->getId()===$e->getId()){$this->manager->stopPlayerControl($p,true);$this->ui->openDisplayMenu($p,$e);return;}
            $this->manager->stopDrag($p);$this->manager->select($p,$e);$this->ui->openDisplayMenu($p,$e);
        }
        public function onDamage(EntityDamageByEntityEvent $ev):void{
            $e=$ev->getEntity();if(!$e instanceof DisplayEntity && !$e instanceof HandleEntity)return;
            $ev->cancel();$p=$ev->getDamager();if(!$p instanceof Player)return;
            $kind=$this->tools->itemKind($p->getInventory()->getItemInHand());
            if($e instanceof HandleEntity){$this->manager->startDrag($p,$e);return;}
            if($kind==='group'){$this->manager->toggleGroupMember($p,$e);return;}
            if($kind==='rotate'){$this->manager->stopDrag($p);$this->manager->select($p,$e);$this->manager->setMode($e,2);$this->manager->refreshHandles($p);$p->sendMessage('§eRotate tool active.');return;}
            if($kind==='scale'){$this->manager->stopDrag($p);$this->manager->select($p,$e);$this->manager->setMode($e,3);$this->manager->refreshHandles($p);$p->sendMessage('§dScale tool active.');return;}
            if($this->manager->hasPlayerControl($p)&&$this->manager->getPlayerControlDisplay($p)?->getId()===$e->getId()){$this->manager->stopPlayerControl($p,true);$this->ui->openDisplayMenu($p,$e);return;}
            $this->manager->stopDrag($p);$this->manager->select($p,$e);$this->ui->openDisplayMenu($p,$e);
        }
        public function onMove(PlayerMoveEvent $ev):void{if($ev->isCancelled())return;$p=$ev->getPlayer();$from=$ev->getFrom();$to=$ev->getTo();if($from->getWorld()->getId()!==$to->getWorld()->getId()){$this->manager->invalidateClientSolidCollision($p);}$this->manager->refreshClientSolidCollision($p);if(!$this->manager->hasPlayerControl($p))$this->manager->resolveSolidMovement($ev);if($this->manager->hasPlayerControl($p))$this->manager->tickPlayerControl($ev);elseif($this->manager->hasActiveDrag($p))$this->manager->tickDrag($p);}
        public function onQuit(PlayerQuitEvent $ev):void{$this->manager->clearClientSolidCollision($ev->getPlayer());$this->manager->stopPlayerControl($ev->getPlayer(),false);$this->manager->clearSelection($ev->getPlayer());}

}
