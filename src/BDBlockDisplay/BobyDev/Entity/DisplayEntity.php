<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev\Entity;

use BDBlockDisplay\BobyDev\Core\BlockRegistry;
use BDBlockDisplay\BobyDev\Core\DisplayMath;
use BDBlockDisplay\BobyDev\Core\NbtUtil;
use BDBlockDisplay\BobyDev\Entity\BaseBDEntity;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;

final class DisplayEntity extends BaseBDEntity{
    public const TAG_BLOCK='BDBlock'; public const TAG_SIZE='BDSize'; public const TAG_RX='BDRx'; public const TAG_RY='BDRy'; public const TAG_RZ='BDRz'; public const TAG_SOLID='BDSolid'; public const TAG_MODE='BDMode'; public const TAG_MODEL='BDModel'; public const TAG_VARIANT='BDVariant'; public const TAG_LIGHT='BDLight'; public const TAG_LIGHT_AT='BDLightAt';
    private string $block='stone'; private float $displaySize=1.0; private bool $solid=false; private int $mode=0; private int $variant=0; private int $light=0; private string $modelIds='';
    public static function getNetworkTypeId():string{return 'bd:display';}
    protected function initEntity(CompoundTag $nbt):void{
        parent::initEntity($nbt); $this->block=$nbt->getString(self::TAG_BLOCK,'stone');$this->displaySize=DisplayMath::clamp($nbt->getFloat(self::TAG_SIZE,1.0),0.0625,16.0);$this->solid=$nbt->getByte(self::TAG_SOLID,0)!==0;$this->mode=$nbt->getInt(self::TAG_MODE,0);$this->variant=$nbt->getInt(self::TAG_VARIANT,0);$this->light=$nbt->getInt(self::TAG_LIGHT,0);
        $this->setScale(max(0.1666667,min(42.6667,$this->displaySize/0.375)));
        $this->setNameTag('');$this->setInvisible(true);
    }
    public function saveNBT():CompoundTag{
        $n=parent::saveNBT(); return $n->setString(self::TAG_BLOCK,$this->block)->setFloat(self::TAG_SIZE,$this->displaySize)->setByte(self::TAG_SOLID,$this->solid?1:0)->setInt(self::TAG_MODE,$this->mode)->setInt(self::TAG_VARIANT,$this->variant)->setInt(self::TAG_LIGHT,$this->light);
    }
    public function setBlockId(string $id):bool{ $c=BlockRegistry::canonical($id); if($c===null)return false; $oldRow=BlockRegistry::row($this->block); $newRow=BlockRegistry::row($c); $this->block=$c; if($oldRow===null || $newRow===null || $oldRow[0]!==$newRow[0]) $this->variant=0; else $this->variant=min($this->variant,BlockRegistry::variantCount($newRow[0])-1); $this->networkPropertiesDirty=true; return true; }
    public function getBlockId():string{return $this->block;}
    public function setDisplaySize(float $size):void{$this->displaySize=DisplayMath::clamp($size,0.0625,16);$this->setScale($this->displaySize/0.375);}
    public function getDisplaySize():float{return $this->displaySize;}
    public function setSolid(bool $v):void{$this->solid=$v;$this->networkPropertiesDirty=true;}
    public function isSolid():bool{return $this->solid;}
    public function setMode(int $m):void{$this->mode=max(0,min(4,$m));$this->networkPropertiesDirty=true;}
    public function getMode():int{return $this->mode;}
    public function setVariant(int $v):void{$row=BlockRegistry::row($this->block);$max=$row===null?1:BlockRegistry::variantCount($row[0]);$this->variant=max(0,min($max-1,$v));$this->networkPropertiesDirty=true;}
    public function getVariant():int{return $this->variant;}
    public function setLightOverride(int $level):void{$this->light=max(-1,min(15,$level));}
    public function getLightOverride():int{return $this->light;}
    public function syncModelMetadata():void{$this->networkPropertiesDirty=true;}
    public function canCollideWith(Entity $entity):bool{return false;}
    /** Virtual collision is resolved in PlayerMoveEvent because Altay 5.44.7 resolves normal movement against world block collision boxes, not arbitrary entity AABBs. */
    protected function syncNetworkData(EntityMetadataCollection $properties):void{parent::syncNetworkData($properties);$properties->setInt(EntityMetadataProperties::VARIANT,$this->mode);$properties->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::HAS_COLLISION,$this->solid);}
}
