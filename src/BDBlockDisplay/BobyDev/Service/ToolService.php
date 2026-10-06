<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev\Service;

use BDBlockDisplay\BobyDev\Core\BlockRegistry;
use BDBlockDisplay\BobyDev\Core\ToolIdRegistry;
use BDBlockDisplay\BobyDev\Display\BlockDisplayManager;
use BDBlockDisplay\BobyDev\Entity\DisplayEntity;
use BDBlockDisplay\BobyDev\Item\GroupToolItem;
use BDBlockDisplay\BobyDev\Item\RotateWandItem;
use BDBlockDisplay\BobyDev\Item\ScaleWandItem;
use BDBlockDisplay\BobyDev\Item\WandItem;
use BDBlockDisplay\BobyDev\Internal\Customies\Item\CustomiesItemFactory;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\player\Player;

final class ToolService{
    public function __construct(private BlockDisplayManager $manager){}

        public function itemKind(Item $i):?string{
            if($i instanceof WandItem)return 'wand';
            if($i instanceof RotateWandItem)return 'rotate';
            if($i instanceof ScaleWandItem)return 'scale';
            if($i instanceof GroupToolItem)return 'group';
            return match($i->getTypeId()){ToolIdRegistry::WAND=>'wand',ToolIdRegistry::ROTATE=>'rotate',ToolIdRegistry::SCALE=>'scale',ToolIdRegistry::GROUP=>'group',default=>null};
        }

        public function giveTools(Player $p):void{$inv=$p->getInventory();foreach(['bd:wand','bd:wand_rotate','bd:wand_scale','bd:group_tool'] as $id){$item=CustomiesItemFactory::getInstance()->get($id);foreach($inv->addItem($item) as $drop){$p->getWorld()->dropItem($p->getPosition(),$drop);}}$p->sendMessage('§aBDBlockDisplay tools given.');}

        private function spawnFromHeld(Player $p):void{$item=$p->getInventory()->getItemInHand();$name=method_exists($item,'getVanillaName')?$item->getVanillaName():$item->getName();$id=strtolower(str_replace([' ','-'],'_',$name));$id=$this->normalizeFromItem($id);if($id===null){$p->sendMessage('§cThis block is not in the BlockDisplay 0.5.1 model database. Use /bdbd spawn <block>.');return;}$pos=$p->getPosition()->addVector($p->getDirectionVector()->multiply(3));$d=new DisplayEntity(new Location($pos->x,$pos->y,$pos->z,$p->getWorld(),$p->getLocation()->yaw,$p->getLocation()->pitch),null);$d->setBlockId($id);$d->setDisplaySize(1);$d->setMode(1);$d->spawnToAll();$this->manager->syncModel($d);$this->manager->select($p,$d);}
        private function normalizeFromItem(string $id):?string{return BlockRegistry::canonical($id) ?? BlockRegistry::canonical(str_replace('minecraft:','',$id));}
}
