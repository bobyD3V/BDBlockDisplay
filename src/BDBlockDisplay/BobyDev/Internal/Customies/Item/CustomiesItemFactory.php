<?php
declare(strict_types=1);
namespace BDBlockDisplay\BobyDev\Internal\Customies\Item;
use Closure;
use InvalidArgumentException;
use BDBlockDisplay\BobyDev\Internal\Customies\Item\Component\ItemComponent;
use BDBlockDisplay\BobyDev\Internal\Customies\Support\SingletonTrait;
use BDBlockDisplay\BobyDev\Internal\Customies\Util\NBT;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use pocketmine\nbt\tag\CompoundTag;
use ReflectionClass;

/** Embedded subset of the MIT-licensed Customies 1.4.x registration layer. */
final class CustomiesItemFactory{
    use SingletonTrait;
    /** @var array<string,ItemTypeEntry> */ private array $itemTableEntries=[];
    public function get(string $identifier,int $amount=1):Item{
        $item=StringToItemParser::getInstance()->parse($identifier);
        if($item===null)throw new InvalidArgumentException('Custom item '.$identifier.' is not registered');
        return $item->setCount($amount);
    }
    /** @param Closure():Item $itemFunc */
    public function registerItem(Closure $itemFunc,string $identifier):void{
        $item=$itemFunc();
        if(!$item instanceof Item)throw new InvalidArgumentException('Registered factory must return an Item');
        $parser=StringToItemParser::getInstance();
        if($parser->parse($identifier)!==null){
            $this->itemTableEntries[$identifier] ??= $this->existingEntry($identifier,$item);
            return;
        }
        GlobalItemDataHandlers::getDeserializer()->map($identifier,static fn(SavedItemData $data):Item=>clone $item);
        GlobalItemDataHandlers::getSerializer()->map($item,static fn(Item $value):SavedItemData=>new SavedItemData($identifier));
        $parser->register($identifier,static fn(string $input):Item=>clone $item);
        $componentBased=$item instanceof ItemComponents;
        $nbt=CompoundTag::create();
        if($componentBased){
            $components=CompoundTag::create();
            $properties=CompoundTag::create();
            foreach($item->getComponents() as $component){
                if(!$component instanceof ItemComponent)continue;
                $tag=NBT::getTagType($component->getValue());
                if($component->isProperty())$properties->setTag($component->getName(),$tag);else$components->setTag($component->getName(),$tag);
            }
            $components->setTag('item_properties',$properties);
            $nbt=CompoundTag::create()->setTag('components',$components)->setInt('id',$item->getTypeId())->setString('name',$identifier);
        }
        $entry=new ItemTypeEntry($identifier,$item->getTypeId(),$componentBased,$componentBased ? 1 : 0,new CacheableNbt($nbt));
        $this->itemTableEntries[$identifier]=$entry;
        $this->registerDictionaryMapping($identifier,$item->getTypeId(),$entry);
    }
    private function existingEntry(string $identifier,Item $item):ItemTypeEntry{
        return new ItemTypeEntry($identifier,$item->getTypeId(),$item instanceof ItemComponents,$item instanceof ItemComponents ? 1 : 0,new CacheableNbt(CompoundTag::create()));
    }
    private function registerDictionaryMapping(string $identifier,int $itemId,ItemTypeEntry $entry):void{
        $dictionary=TypeConverter::getInstance()->getItemTypeDictionary();
        $reflection=new ReflectionClass($dictionary);
        $intToString=$reflection->getProperty('intToStringIdMap');$map=$intToString->getValue($dictionary);$map[$itemId]=$identifier;$intToString->setValue($dictionary,$map);
        $stringToInt=$reflection->getProperty('stringToIntMap');$map=$stringToInt->getValue($dictionary);$map[$identifier]=$itemId;$stringToInt->setValue($dictionary,$map);
        $items=$reflection->getProperty('itemTypes');$entries=$items->getValue($dictionary);$entries[]=$entry;$items->setValue($dictionary,$entries);
    }
}
