<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev\Entity;

use BDBlockDisplay\BobyDev\Core\NbtUtil;
use BDBlockDisplay\BobyDev\Entity\BaseBDEntity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;

class HandleEntity extends BaseBDEntity implements NeverSavedWithChunkEntity{
    protected static string $NETWORK='bd:handle'; public int $kind=0; public int $color=0; public bool $hot=false; public int $axis=0; public int $sign=1; public ?int $ownerDisplayId=null;
    protected function getInitialSizeInfo(): EntitySizeInfo{return new EntitySizeInfo(0.4,0.4);}
    public static function getNetworkTypeId():string{return static::$NETWORK;}
    protected function syncNetworkData(EntityMetadataCollection $properties):void{parent::syncNetworkData($properties);$properties->setInt(EntityMetadataProperties::VARIANT,$this->kind);$properties->setInt(EntityMetadataProperties::MARK_VARIANT,$this->color);$properties->setInt(EntityMetadataProperties::SKIN_ID,$this->hot?1:0);$properties->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::HAS_COLLISION,false);}
    public function saveNBT():CompoundTag{$n=parent::saveNBT();return $n->setInt('BDKind',$this->kind)->setInt('BDColor',$this->color)->setByte('BDHot',$this->hot?1:0)->setInt('BDAxis',$this->axis)->setInt('BDSign',$this->sign)->setInt('BDOwner',$this->ownerDisplayId??-1);}
    protected function initEntity(CompoundTag $nbt):void{parent::initEntity($nbt);$this->kind=NbtUtil::readIntTag($nbt,'BDKind',0);$this->color=NbtUtil::readIntTag($nbt,'BDColor',0);$this->hot=$nbt->getByte('BDHot',0)!==0;$this->axis=NbtUtil::readIntTag($nbt,'BDAxis',0);$this->sign=NbtUtil::readIntTag($nbt,'BDSign',1);$o=NbtUtil::readIntTag($nbt,'BDOwner',-1);$this->ownerDisplayId=$o>=0?$o:null;}
    public function setHot(bool $v):void{$this->hot=$v;$this->networkPropertiesDirty=true;}
}
