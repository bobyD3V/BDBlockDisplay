<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev\Entity;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\player\Player;

abstract class BaseBDEntity extends Entity{
    protected float $roll=0.0;
    protected function getInitialSizeInfo(): EntitySizeInfo{return new EntitySizeInfo(0.375,0.375);}
    protected function getInitialDragMultiplier(): float{return 0.0;}
    protected function getInitialGravity(): float{return 0.0;}
    public function canCollideWith(Entity $entity):bool{return false;}
    public function getRoll():float{return $this->roll;}
    public function setRoll(float $roll):void{
        $this->roll=fmod($roll,360.0); if($this->roll < -180)$this->roll+=360; if($this->roll>180)$this->roll-=360;
        $this->scheduleUpdate();
        $this->broadcastCustomMovement(true);
    }
    public function setRotation(float $yaw,float $pitch):void{parent::setRotation($yaw,$pitch);$this->broadcastCustomMovement(true);}
    protected function broadcastMovement(bool $teleport=false):void{$this->broadcastCustomMovement($teleport);}
    protected function broadcastCustomMovement(bool $teleport=false):void{
        if($this->getViewers()===[]) return;
        $flags=$this->onGround ? MoveActorAbsolutePacket::FLAG_GROUND : 0;
        if($teleport) $flags|=MoveActorAbsolutePacket::FLAG_TELEPORT;
        NetworkBroadcastUtils::broadcastPackets($this->getViewers(),[MoveActorAbsolutePacket::create($this->getId(),$this->getOffsetPosition($this->getPosition()),$this->getLocation()->pitch,$this->getLocation()->yaw,$this->roll,$flags)]);
    }
    protected function sendSpawnPacket(Player $player):void{
        parent::sendSpawnPacket($player);
        $flags=$this->onGround ? MoveActorAbsolutePacket::FLAG_GROUND : 0;
        $player->getNetworkSession()->sendDataPacket(MoveActorAbsolutePacket::create($this->getId(),$this->getOffsetPosition($this->getPosition()),$this->getLocation()->pitch,$this->getLocation()->yaw,$this->roll,$flags));
    }
    public function saveNBT():CompoundTag{
        $nbt=parent::saveNBT(); $nbt->setFloat('BDBDRotationZ',$this->roll); return $nbt;
    }
    protected function initEntity(CompoundTag $nbt):void{parent::initEntity($nbt);$this->roll=$nbt->getFloat('BDBDRotationZ',0.0);}
    protected function syncNetworkData(EntityMetadataCollection $properties):void{parent::syncNetworkData($properties);}
}
