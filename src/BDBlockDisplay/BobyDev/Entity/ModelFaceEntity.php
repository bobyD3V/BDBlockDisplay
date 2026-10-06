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

class ModelFaceEntity extends BaseBDEntity implements NeverSavedWithChunkEntity{
    protected function getInitialSizeInfo(): EntitySizeInfo{return new EntitySizeInfo(0.01,0.01);}
    protected static string $NETWORK='bd:model_face'; protected int $texture=0; protected int $shape=1; protected int $shapeVariant=0; protected int $move=0; protected string $face='up'; public int $ownerDisplayId=-1;
    public static function getNetworkTypeId():string{return static::$NETWORK;}
    public function configure(int $texture,int $shape,int $variant,string $face,float $scale,float $yaw,float $pitch,float $roll,int $ownerId,int $move=0):void{$this->texture=max(0,min(479,$texture));$this->shape=$shape;$this->shapeVariant=$variant;$this->face=$face;$this->ownerDisplayId=$ownerId;$this->move=max(0,min(6,$move));$this->setScale($scale);$this->setRotation($yaw,$pitch);$this->setRoll($roll);$this->networkPropertiesDirty=true;}
    public function getTexture():int{return $this->texture;}
    public function saveNBT():CompoundTag{ $n=parent::saveNBT(); return $n->setInt('BDTexture',$this->texture)->setInt('BDShape',$this->shape)->setInt('BDShapeVariant',$this->shapeVariant)->setInt('BDMove',$this->move)->setString('BDFace',$this->face)->setInt('BDOwnerDisplay',$this->ownerDisplayId); }
    protected function initEntity(CompoundTag $nbt):void{parent::initEntity($nbt);$this->texture=NbtUtil::readIntTag($nbt,'BDTexture',0);$this->shape=NbtUtil::readIntTag($nbt,'BDShape',1);$this->shapeVariant=NbtUtil::readIntTag($nbt,'BDShapeVariant',0);$this->move=NbtUtil::readIntTag($nbt,'BDMove',0);$this->face=$nbt->getString('BDFace','up');$this->ownerDisplayId=NbtUtil::readIntTag($nbt,'BDOwnerDisplay',-1);$this->networkPropertiesDirty=true;}
    protected function syncNetworkData(EntityMetadataCollection $properties):void{parent::syncNetworkData($properties);$properties->setInt(EntityMetadataProperties::VARIANT,$this->texture);$properties->setInt(EntityMetadataProperties::MARK_VARIANT,$this->move);$properties->setInt(EntityMetadataProperties::SKIN_ID,$this->shape*10+$this->shapeVariant);$properties->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::HAS_COLLISION,false);}
}
