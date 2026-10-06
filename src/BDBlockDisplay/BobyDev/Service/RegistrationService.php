<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev\Service;

use BDBlockDisplay\BobyDev\Core\ToolIdRegistry;
use BDBlockDisplay\BobyDev\Entity\DisplayEntity;
use BDBlockDisplay\BobyDev\Entity\HandleEntity;
use BDBlockDisplay\BobyDev\Item\GroupToolItem;
use BDBlockDisplay\BobyDev\Item\RotateWandItem;
use BDBlockDisplay\BobyDev\Item\ScaleWandItem;
use BDBlockDisplay\BobyDev\Item\WandItem;
use BDBlockDisplay\BobyDev\Model\ModelRegistryBootstrap;
use BDBlockDisplay\BobyDev\main;
use BDBlockDisplay\BobyDev\Internal\Customies\Entity\CustomiesEntityFactory;
use BDBlockDisplay\BobyDev\Internal\Customies\Item\CustomiesItemFactory;
use pocketmine\item\ItemIdentifier;
use pocketmine\resourcepacks\ZippedResourcePack;

final class RegistrationService{
    public function __construct(private main $plugin){}
    public function registerAll():void{
        ModelRegistryBootstrap::register();
        $this->registerEntities();
        $this->registerItems();
        $this->installResourcePack();
    }

        private function registerEntities():void{
            CustomiesEntityFactory::getInstance()->registerEntity(DisplayEntity::class,'bd:display');CustomiesEntityFactory::getInstance()->registerEntity(HandleEntity::class,'bd:handle');
            foreach(ModelRegistryBootstrap::all() as $pair){CustomiesEntityFactory::getInstance()->registerEntity($pair[0],$pair[1]);}
        }
        private function registerItems():void{
            CustomiesItemFactory::getInstance()->registerItem(fn()=>new WandItem(new ItemIdentifier(ToolIdRegistry::WAND)),'bd:wand');
            CustomiesItemFactory::getInstance()->registerItem(fn()=>new RotateWandItem(new ItemIdentifier(ToolIdRegistry::ROTATE)),'bd:wand_rotate');
            CustomiesItemFactory::getInstance()->registerItem(fn()=>new ScaleWandItem(new ItemIdentifier(ToolIdRegistry::SCALE)),'bd:wand_scale');
            CustomiesItemFactory::getInstance()->registerItem(fn()=>new GroupToolItem(new ItemIdentifier(ToolIdRegistry::GROUP)),'bd:group_tool');
        }

        private function installResourcePack():void{
            $src=$this->plugin->getResource('BDBlockDisplay_RP.mcpack');
            if($src===null)return;
            $path=$this->plugin->getDataFolder().'BDBlockDisplay_RP.mcpack';
            @mkdir($this->plugin->getDataFolder(),0777,true);
            file_put_contents($path,stream_get_contents($src));
            fclose($src);
            $pack=new ZippedResourcePack($path);
            $rm=$this->plugin->getServer()->getResourcePackManager();
            $stack=$rm->getResourceStack();
            // Altay applies the first pack in the stack at highest priority.
            // Keep all existing WodCraft packs above this pack so BDBlockDisplay
            // cannot override ScoreHud/resource-pack assets.
            $out=[];
            foreach($stack as $p){
                if(strtolower($p->getPackId()) !== strtolower($pack->getPackId())){
                    $out[]=$p;
                }
            }
            $out[]=$pack;
            $rm->setResourceStack($out);
        }
}
