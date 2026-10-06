<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev\Internal\Customies\Entity;

use Closure;
use BDBlockDisplay\BobyDev\Internal\Customies\Support\SingletonTrait;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\cache\StaticPacketCache;
use pocketmine\network\mcpe\protocol\AvailableActorIdentifiersPacket;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\world\World;
use ReflectionClass;

/**
 * Embedded Customies-compatible entity registration required by BDBlockDisplay.
 * Derived from the public Customies 1.4.x registration mechanism, kept in this
 * plugin so external Customies is not required at runtime.
 */
final class CustomiesEntityFactory{
    use SingletonTrait;

    /** @param class-string<Entity> $className */
    public function registerEntity(string $className,string $identifier,?Closure $creationFunc=null,string $behaviourId=''):void{
        if(!$this->isEntityRegistered($className,$identifier)){
            EntityFactory::getInstance()->register(
                $className,
                $creationFunc ?? static function(World $world,CompoundTag $nbt)use($className):Entity{
                    return new $className(EntityDataHelper::parseLocation($nbt,$world),$nbt);
                },
                [$identifier]
            );
            $this->updateStaticPacketCache($identifier,$behaviourId);
        }
    }

    private function isEntityRegistered(string $className,string $identifier):bool{
        $factory=EntityFactory::getInstance();
        $reflection=new ReflectionClass($factory);

        $saveNames=$reflection->getProperty('saveNames')->getValue($factory);
        if(isset($saveNames[$className])){
            return true;
        }

        $creationFuncs=$reflection->getProperty('creationFuncs')->getValue($factory);
        return isset($creationFuncs[$identifier]);
    }

    private function updateStaticPacketCache(string $identifier,string $behaviourId):void{
        $instance=StaticPacketCache::getInstance();
        $reflection=new ReflectionClass($instance);
        $property=$reflection->getProperty('availableActorIdentifiers');
        /** @var AvailableActorIdentifiersPacket $packet */
        $packet=$property->getValue($instance);
        $root=$packet->identifiers->getRoot();
        $idList=$root->getListTag('idlist');
        if($idList===null){
            $idList=new ListTag();
            $root->setTag('idlist',$idList);
        }
        foreach($idList as $entry){
            if($entry instanceof CompoundTag && $entry->getString('id','') === $identifier){
                return;
            }
        }
        $idList->push(CompoundTag::create()->setString('id',$identifier)->setString('bid',$behaviourId));
        $packet->identifiers=new CacheableNbt($root);
    }
}
