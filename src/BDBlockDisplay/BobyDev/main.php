<?php
declare(strict_types=1);

namespace BDBlockDisplay\BobyDev;

use customiesdevs\customies\entity\CustomiesEntityFactory;
use customiesdevs\customies\item\CustomiesItemFactory;
use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\ItemComponentsTrait;
use customiesdevs\customies\item\component\UseAnimationComponent;
use customiesdevs\customies\item\component\InteractButtonComponent;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\Cancellable;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\math\Vector3;
use pocketmine\math\AxisAlignedBB;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\entity\Location;
use pocketmine\world\World;
use pocketmine\utils\TextFormat;

/**
 * Reads integer-like NBT safely across old BDBlockDisplay saves.
 * Altay's CompoundTag::getInt() is intentionally type-strict, while
 * older BDBlockDisplay builds stored entity owner IDs as LongTag.
 */
function bdReadIntTag(CompoundTag $nbt, string $name, int $default): int{
    $tag = $nbt->getTag($name);
    if($tag === null){
        return $default;
    }
    $value = $tag->getValue();
    return is_int($value) ? $value : $default;
}

const BD_VERSION = '1.0.0';

final class BlockRegistry{
    /** @var array<string,array{0:int,1:int,2:int,3:int,4:int,5:int,6:int}> */
    public const BLOCKS = ["acacia_double_slab"=>[1,0,0,0,0,0,0],"acacia_fence"=>[4,0,0,0,0,0,0],"acacia_leaves"=>[1,1,1,1,1,1,1],"acacia_log"=>[1,2,2,3,3,3,3],"acacia_planks"=>[1,0,0,0,0,0,0],"acacia_shelf"=>[1,4,4,4,4,4,4],"acacia_slab"=>[2,0,0,0,0,0,0],"acacia_stairs"=>[3,0,0,0,0,0,0],"acacia_wood"=>[1,3,3,3,3,3,3],"amethyst_block"=>[1,5,5,5,5,5,5],"ancient_debris"=>[1,6,6,7,7,7,7],"andesite"=>[1,8,8,8,8,8,8],"andesite_double_slab"=>[1,8,8,8,8,8,8],"andesite_slab"=>[2,8,8,8,8,8,8],"andesite_stairs"=>[3,8,8,8,8,8,8],"azalea_leaves"=>[1,9,9,9,9,9,9],"azalea_leaves_flowered"=>[1,10,10,10,10,10,10],"bamboo_block"=>[1,11,11,12,12,12,12],"bamboo_double_slab"=>[1,13,13,13,13,13,13],"bamboo_fence"=>[4,14,14,14,14,14,14],"bamboo_mosaic_double_slab"=>[1,15,15,15,15,15,15],"bamboo_mosaic_slab"=>[2,15,15,15,15,15,15],"bamboo_mosaic_stairs"=>[3,15,15,15,15,15,15],"bamboo_slab"=>[2,13,13,13,13,13,13],"bamboo_stairs"=>[3,13,13,13,13,13,13],"barrel"=>[1,16,16,17,18,16,16],"basalt"=>[1,19,19,20,20,20,20],"bedrock"=>[1,21,21,21,21,21,21],"bee_nest"=>[1,22,23,24,25,24,24],"beehive"=>[1,26,26,27,28,27,27],"birch_double_slab"=>[1,29,29,29,29,29,29],"birch_fence"=>[4,29,29,29,29,29,29],"birch_leaves"=>[1,30,30,30,30,30,30],"birch_log"=>[1,31,31,32,32,32,32],"birch_planks"=>[1,29,29,29,29,29,29],"birch_shelf"=>[1,33,33,33,33,33,33],"birch_slab"=>[2,29,29,29,29,29,29],"birch_stairs"=>[3,29,29,29,29,29,29],"birch_wood"=>[1,32,32,32,32,32,32],"black_concrete"=>[1,34,34,34,34,34,34],"black_concrete_powder"=>[1,35,35,35,35,35,35],"black_glazed_terracotta"=>[1,36,36,36,36,36,36],"black_stained_glass"=>[1,37,37,37,37,37,37],"black_terracotta"=>[1,38,38,38,38,38,38],"black_wool"=>[1,39,39,39,39,39,39],"blackstone"=>[1,40,40,41,41,41,41],"blackstone_double_slab"=>[1,41,41,41,41,41,41],"blackstone_slab"=>[2,41,41,41,41,41,41],"blackstone_stairs"=>[3,41,41,41,41,41,41],"blast_furnace"=>[1,42,42,43,44,43,43],"blue_concrete"=>[1,45,45,45,45,45,45],"blue_concrete_powder"=>[1,46,46,46,46,46,46],"blue_glazed_terracotta"=>[1,47,47,47,47,47,47],"blue_ice"=>[1,48,48,48,48,48,48],"blue_stained_glass"=>[1,49,49,49,49,49,49],"blue_terracotta"=>[1,50,50,50,50,50,50],"blue_wool"=>[1,51,51,51,51,51,51],"bone_block"=>[1,52,52,53,53,53,53],"bookshelf"=>[1,54,54,55,55,55,55],"border_block"=>[1,56,56,56,56,56,56],"brain_coral_block"=>[1,57,57,57,57,57,57],"brick_block"=>[1,58,58,58,58,58,58],"brick_double_slab"=>[1,58,58,58,58,58,58],"brick_slab"=>[2,58,58,58,58,58,58],"brick_stairs"=>[3,58,58,58,58,58,58],"brown_concrete"=>[1,59,59,59,59,59,59],"brown_concrete_powder"=>[1,60,60,60,60,60,60],"brown_glazed_terracotta"=>[1,61,61,61,61,61,61],"brown_mushroom_block"=>[1,62,62,62,62,62,62],"brown_stained_glass"=>[1,63,63,63,63,63,63],"brown_terracotta"=>[1,64,64,64,64,64,64],"brown_wool"=>[1,65,65,65,65,65,65],"bubble_coral_block"=>[1,66,66,66,66,66,66],"budding_amethyst"=>[1,67,67,67,67,67,67],"calcite"=>[1,68,68,68,68,68,68],"cartography_table"=>[1,69,70,71,72,71,73],"carved_pumpkin"=>[1,74,74,75,76,75,75],"cherry_double_slab"=>[1,77,77,77,77,77,77],"cherry_fence"=>[4,77,77,77,77,77,77],"cherry_leaves"=>[1,78,78,78,78,78,78],"cherry_log"=>[1,79,79,80,80,80,80],"cherry_planks"=>[1,77,77,77,77,77,77],"cherry_shelf"=>[1,81,81,81,81,81,81],"cherry_slab"=>[2,77,77,77,77,77,77],"cherry_stairs"=>[3,77,77,77,77,77,77],"cherry_wood"=>[1,80,80,80,80,80,80],"chiseled_bookshelf"=>[1,82,82,83,84,84,84],"chiseled_cinnabar"=>[1,85,85,85,85,85,85],"chiseled_copper"=>[1,86,86,86,86,86,86],"chiseled_deepslate"=>[1,87,87,87,87,87,87],"chiseled_nether_bricks"=>[1,88,88,88,88,88,88],"chiseled_polished_blackstone"=>[1,89,89,89,89,89,89],"chiseled_quartz_block"=>[1,90,90,91,91,91,91],"chiseled_red_sandstone"=>[1,92,92,93,93,93,93],"chiseled_resin_bricks"=>[1,94,94,94,94,94,94],"chiseled_sandstone"=>[1,95,95,96,96,96,96],"chiseled_stone_bricks"=>[1,97,97,97,97,97,97],"chiseled_sulfur"=>[1,98,98,98,98,98,98],"chiseled_tuff"=>[1,99,99,100,100,100,100],"chiseled_tuff_bricks"=>[1,101,101,102,102,102,102],"cinnabar"=>[1,103,103,103,103,103,103],"cinnabar_brick_double_slab"=>[1,104,104,104,104,104,104],"cinnabar_brick_slab"=>[2,104,104,104,104,104,104],"cinnabar_brick_stairs"=>[3,104,104,104,104,104,104],"cinnabar_bricks"=>[1,104,104,104,104,104,104],"cinnabar_double_slab"=>[1,103,103,103,103,103,103],"cinnabar_slab"=>[2,103,103,103,103,103,103],"cinnabar_stairs"=>[3,103,103,103,103,103,103],"clay"=>[1,105,105,105,105,105,105],"closed_eyeblossom"=>[1,106,106,106,106,106,106],"coal_block"=>[1,107,107,107,107,107,107],"coal_ore"=>[1,108,108,108,108,108,108],"coarse_dirt"=>[1,109,109,109,109,109,109],"cobbled_deepslate"=>[1,110,110,110,110,110,110],"cobbled_deepslate_double_slab"=>[1,110,110,110,110,110,110],"cobbled_deepslate_slab"=>[2,110,110,110,110,110,110],"cobbled_deepslate_stairs"=>[3,110,110,110,110,110,110],"cobblestone"=>[1,111,111,111,111,111,111],"cobblestone_double_slab"=>[1,111,111,111,111,111,111],"cobblestone_slab"=>[2,111,111,111,111,111,111],"concrete"=>[1,112,112,112,112,112,112],"concretePowder"=>[1,113,113,113,113,113,113],"copper_block"=>[1,114,114,114,114,114,114],"copper_bulb"=>[1,115,115,115,115,115,115],"copper_golem_statue"=>[1,114,114,114,114,114,114],"copper_grate"=>[1,116,116,116,116,116,116],"copper_ore"=>[1,117,117,117,117,117,117],"coral_block"=>[1,118,118,118,118,118,118],"cracked_deepslate_bricks"=>[1,119,119,119,119,119,119],"cracked_deepslate_tiles"=>[1,120,120,120,120,120,120],"cracked_nether_bricks"=>[1,121,121,121,121,121,121],"cracked_polished_blackstone_bricks"=>[1,122,122,122,122,122,122],"cracked_stone_bricks"=>[1,123,123,123,123,123,123],"crafter"=>[1,124,125,126,127,128,129],"crafting_table"=>[1,130,54,131,131,132,132],"creaking_heart"=>[1,133,133,134,134,134,134],"crimson_double_slab"=>[1,135,135,135,135,135,135],"crimson_fence"=>[4,135,135,135,135,135,135],"crimson_nylium"=>[1,136,137,138,138,138,138],"crimson_planks"=>[1,135,135,135,135,135,135],"crimson_shelf"=>[1,139,139,139,139,139,139],"crimson_slab"=>[2,135,135,135,135,135,135],"crimson_stairs"=>[3,135,135,135,135,135,135],"crying_obsidian"=>[1,141,141,141,141,141,141],"cut_copper"=>[1,142,142,142,142,142,142],"cut_copper_slab"=>[2,142,142,142,142,142,142],"cut_copper_stairs"=>[3,142,142,142,142,142,142],"cut_red_sandstone"=>[1,92,92,143,143,143,143],"cut_red_sandstone_double_slab"=>[1,92,92,143,143,143,143],"cut_red_sandstone_slab"=>[2,92,92,143,143,143,143],"cut_sandstone"=>[1,95,95,144,144,144,144],"cut_sandstone_double_slab"=>[1,95,95,144,144,144,144],"cut_sandstone_slab"=>[2,95,95,144,144,144,144],"cyan_concrete"=>[1,145,145,145,145,145,145],"cyan_concrete_powder"=>[1,146,146,146,146,146,146],"cyan_glazed_terracotta"=>[1,147,147,147,147,147,147],"cyan_stained_glass"=>[1,148,148,148,148,148,148],"cyan_terracotta"=>[1,149,149,149,149,149,149],"cyan_wool"=>[1,150,150,150,150,150,150],"dark_oak_double_slab"=>[1,70,70,70,70,70,70],"dark_oak_fence"=>[4,70,70,70,70,70,70],"dark_oak_leaves"=>[1,151,151,151,151,151,151],"dark_oak_log"=>[1,152,152,153,153,153,153],"dark_oak_planks"=>[1,70,70,70,70,70,70],"dark_oak_shelf"=>[1,154,154,154,154,154,154],"dark_oak_slab"=>[2,70,70,70,70,70,70],"dark_oak_stairs"=>[3,70,70,70,70,70,70],"dark_oak_wood"=>[1,153,153,153,153,153,153],"dark_prismarine"=>[1,155,155,155,155,155,155],"dark_prismarine_double_slab"=>[1,155,155,155,155,155,155],"dark_prismarine_slab"=>[2,155,155,155,155,155,155],"dark_prismarine_stairs"=>[3,155,155,155,155,155,155],"dead_brain_coral_block"=>[1,156,156,156,156,156,156],"dead_bubble_coral_block"=>[1,157,157,157,157,157,157],"dead_fire_coral_block"=>[1,158,158,158,158,158,158],"dead_horn_coral_block"=>[1,159,159,159,159,159,159],"dead_tube_coral_block"=>[1,160,160,160,160,160,160],"deepslate"=>[1,161,161,162,162,162,162],"deepslate_brick_double_slab"=>[1,163,163,163,163,163,163],"deepslate_brick_slab"=>[2,163,163,163,163,163,163],"deepslate_brick_stairs"=>[3,163,163,163,163,163,163],"deepslate_bricks"=>[1,163,163,163,163,163,163],"deepslate_coal_ore"=>[1,164,164,164,164,164,164],"deepslate_copper_ore"=>[1,165,165,165,165,165,165],"deepslate_diamond_ore"=>[1,166,166,166,166,166,166],"deepslate_emerald_ore"=>[1,167,167,167,167,167,167],"deepslate_gold_ore"=>[1,168,168,168,168,168,168],"deepslate_iron_ore"=>[1,169,169,169,169,169,169],"deepslate_lapis_ore"=>[1,170,170,170,170,170,170],"deepslate_redstone_ore"=>[1,171,171,171,171,171,171],"deepslate_tile_double_slab"=>[1,172,172,172,172,172,172],"deepslate_tile_slab"=>[2,172,172,172,172,172,172],"deepslate_tile_stairs"=>[3,172,172,172,172,172,172],"deepslate_tiles"=>[1,172,172,172,172,172,172],"deprecated_purpur_block_1"=>[1,173,173,173,173,173,173],"deprecated_purpur_block_2"=>[1,173,173,173,173,173,173],"diamond_block"=>[1,174,174,174,174,174,174],"diamond_ore"=>[1,175,175,175,175,175,175],"diorite"=>[1,176,176,176,176,176,176],"diorite_double_slab"=>[1,176,176,176,176,176,176],"diorite_slab"=>[2,176,176,176,176,176,176],"diorite_stairs"=>[3,176,176,176,176,176,176],"dirt"=>[1,177,177,177,177,177,177],"dirt_with_roots"=>[1,178,178,178,178,178,178],"dispenser"=>[1,179,179,180,181,180,180],"double_plant"=>[1,182,182,182,182,182,182],"dried_kelp_block"=>[1,183,183,184,185,185,184],"dripstone_block"=>[1,186,186,186,186,186,186],"dropper"=>[1,179,179,180,187,180,180],"emerald_block"=>[1,188,188,188,188,188,188],"emerald_ore"=>[1,189,189,189,189,189,189],"end_brick_stairs"=>[3,190,190,190,190,190,190],"end_bricks"=>[1,190,190,190,190,190,190],"end_stone"=>[1,191,191,191,191,191,191],"end_stone_brick_double_slab"=>[1,190,190,190,190,190,190],"end_stone_brick_slab"=>[2,190,190,190,190,190,190],"exposed_chiseled_copper"=>[1,192,192,192,192,192,192],"exposed_copper"=>[1,193,193,193,193,193,193],"exposed_copper_bulb"=>[1,194,194,194,194,194,194],"exposed_copper_golem_statue"=>[1,193,193,193,193,193,193],"exposed_copper_grate"=>[1,195,195,195,195,195,195],"exposed_cut_copper"=>[1,196,196,196,196,196,196],"exposed_cut_copper_slab"=>[2,196,196,196,196,196,196],"exposed_cut_copper_stairs"=>[3,196,196,196,196,196,196],"farmland"=>[1,197,177,177,177,177,177],"fence"=>[4,54,54,54,54,54,54],"fire_coral_block"=>[1,198,198,198,198,198,198],"fletching_table"=>[1,199,29,200,200,201,201],"frosted_ice"=>[1,202,202,202,202,202,202],"furnace"=>[1,179,179,180,203,180,180],"gilded_blackstone"=>[1,204,204,204,204,204,204],"glass"=>[1,205,205,205,205,205,205],"glowingobsidian"=>[1,206,206,206,206,206,206],"glowstone"=>[1,207,207,207,207,207,207],"gold_block"=>[1,208,208,208,208,208,208],"gold_ore"=>[1,209,209,209,209,209,209],"granite"=>[1,210,210,210,210,210,210],"granite_double_slab"=>[1,210,210,210,210,210,210],"granite_slab"=>[2,210,210,210,210,210,210],"granite_stairs"=>[3,210,210,210,210,210,210],"grass"=>[1,211,177,212,212,212,212],"grass_path"=>[1,213,177,214,214,214,214],"gravel"=>[1,215,215,215,215,215,215],"gray_concrete"=>[1,216,216,216,216,216,216],"gray_concrete_powder"=>[1,217,217,217,217,217,217],"gray_glazed_terracotta"=>[1,218,218,218,218,218,218],"gray_stained_glass"=>[1,219,219,219,219,219,219],"gray_terracotta"=>[1,220,220,220,220,220,220],"gray_wool"=>[1,221,221,221,221,221,221],"green_concrete"=>[1,222,222,222,222,222,222],"green_concrete_powder"=>[1,223,223,223,223,223,223],"green_glazed_terracotta"=>[1,224,224,224,224,224,224],"green_stained_glass"=>[1,225,225,225,225,225,225],"green_terracotta"=>[1,226,226,226,226,226,226],"green_wool"=>[1,227,227,227,227,227,227],"hardened_clay"=>[1,228,228,228,228,228,228],"hay_block"=>[1,229,229,230,230,230,230],"honey_block"=>[1,231,232,233,233,233,233],"honeycomb_block"=>[1,234,234,234,234,234,234],"horn_coral_block"=>[1,235,235,235,235,235,235],"ice"=>[1,236,236,236,236,236,236],"infested_chiseled_stone_bricks"=>[1,97,97,97,97,97,97],"infested_cobblestone"=>[1,111,111,111,111,111,111],"infested_cracked_stone_bricks"=>[1,123,123,123,123,123,123],"infested_deepslate"=>[1,161,161,162,162,162,162],"infested_mossy_stone_bricks"=>[1,237,237,237,237,237,237],"infested_stone"=>[1,238,238,238,238,238,238],"infested_stone_bricks"=>[1,239,239,239,239,239,239],"iron_block"=>[1,240,240,240,240,240,240],"iron_ore"=>[1,241,241,241,241,241,241],"jukebox"=>[1,242,243,243,243,243,243],"jungle_double_slab"=>[1,244,244,244,244,244,244],"jungle_fence"=>[4,244,244,244,244,244,244],"jungle_leaves"=>[1,245,245,245,245,245,245],"jungle_log"=>[1,246,246,247,247,247,247],"jungle_planks"=>[1,244,244,244,244,244,244],"jungle_shelf"=>[1,248,248,248,248,248,248],"jungle_slab"=>[2,244,244,244,244,244,244],"jungle_stairs"=>[3,244,244,244,244,244,244],"jungle_wood"=>[1,247,247,247,247,247,247],"lapis_block"=>[1,249,249,249,249,249,249],"lapis_ore"=>[1,250,250,250,250,250,250],"leaves"=>[1,251,251,251,251,251,251],"leaves2"=>[1,1,1,1,1,1,1],"light_blue_concrete"=>[1,252,252,252,252,252,252],"light_blue_concrete_powder"=>[1,253,253,253,253,253,253],"light_blue_glazed_terracotta"=>[1,254,254,254,254,254,254],"light_blue_stained_glass"=>[1,255,255,255,255,255,255],"light_blue_terracotta"=>[1,256,256,256,256,256,256],"light_blue_wool"=>[1,257,257,257,257,257,257],"light_gray_concrete"=>[1,258,258,258,258,258,258],"light_gray_concrete_powder"=>[1,259,259,259,259,259,259],"light_gray_stained_glass"=>[1,260,260,260,260,260,260],"light_gray_terracotta"=>[1,261,261,261,261,261,261],"light_gray_wool"=>[1,262,262,262,262,262,262],"lime_concrete"=>[1,263,263,263,263,263,263],"lime_concrete_powder"=>[1,264,264,264,264,264,264],"lime_glazed_terracotta"=>[1,265,265,265,265,265,265],"lime_stained_glass"=>[1,266,266,266,266,266,266],"lime_terracotta"=>[1,267,267,267,267,267,267],"lime_wool"=>[1,268,268,268,268,268,268],"lit_deepslate_redstone_ore"=>[1,171,171,171,171,171,171],"lit_furnace"=>[1,179,179,180,180,269,180],"lit_pumpkin"=>[1,74,74,75,76,75,75],"lit_redstone_lamp"=>[1,270,270,270,270,270,270],"lit_redstone_ore"=>[1,271,271,271,271,271,271],"lodestone"=>[1,275,275,276,276,276,276],"log"=>[1,277,277,278,278,278,278],"log2"=>[1,2,2,3,3,3,3],"loom"=>[1,279,280,281,282,282,282],"magenta_concrete"=>[1,283,283,283,283,283,283],"magenta_concrete_powder"=>[1,284,284,284,284,284,284],"magenta_glazed_terracotta"=>[1,285,285,285,285,285,285],"magenta_stained_glass"=>[1,286,286,286,286,286,286],"magenta_terracotta"=>[1,287,287,287,287,287,287],"magenta_wool"=>[1,288,288,288,288,288,288],"mangrove_double_slab"=>[1,289,289,289,289,289,289],"mangrove_fence"=>[4,289,289,289,289,289,289],"mangrove_leaves"=>[1,290,290,290,290,290,290],"mangrove_log"=>[1,291,291,292,292,292,292],"mangrove_planks"=>[1,289,289,289,289,289,289],"mangrove_propagule"=>[1,293,293,293,293,293,293],"mangrove_shelf"=>[1,294,294,294,294,294,294],"mangrove_slab"=>[2,289,289,289,289,289,289],"mangrove_stairs"=>[3,289,289,289,289,289,289],"mangrove_wood"=>[1,292,292,292,292,292,292],"melon_block"=>[1,295,295,296,296,296,296],"mob_spawner"=>[1,297,297,297,297,297,297],"moss_block"=>[1,298,298,298,298,298,298],"mossy_cobblestone"=>[1,299,299,299,299,299,299],"mossy_cobblestone_double_slab"=>[1,299,299,299,299,299,299],"mossy_cobblestone_slab"=>[2,299,299,299,299,299,299],"mossy_cobblestone_stairs"=>[3,299,299,299,299,299,299],"mossy_stone_brick_double_slab"=>[1,237,237,237,237,237,237],"mossy_stone_brick_slab"=>[2,237,237,237,237,237,237],"mossy_stone_brick_stairs"=>[3,237,237,237,237,237,237],"mossy_stone_bricks"=>[1,237,237,237,237,237,237],"mud"=>[1,300,300,300,300,300,300],"mud_brick_double_slab"=>[1,301,301,301,301,301,301],"mud_brick_slab"=>[2,301,301,301,301,301,301],"mud_brick_stairs"=>[3,301,301,301,301,301,301],"mud_bricks"=>[1,301,301,301,301,301,301],"muddy_mangrove_roots"=>[1,302,302,303,303,303,303],"mushroom_stem"=>[1,304,304,304,304,304,304],"mycelium"=>[1,305,177,306,306,306,306],"nether_brick"=>[1,307,307,307,307,307,307],"nether_brick_double_slab"=>[1,307,307,307,307,307,307],"nether_brick_fence"=>[4,307,307,307,307,307,307],"nether_brick_slab"=>[2,307,307,307,307,307,307],"nether_brick_stairs"=>[3,307,307,307,307,307,307],"nether_gold_ore"=>[1,308,308,308,308,308,308],"nether_wart_block"=>[1,309,309,309,309,309,309],"netherite_block"=>[1,310,310,310,310,310,310],"netherrack"=>[1,137,137,137,137,137,137],"netherreactor"=>[1,311,311,311,311,311,311],"normal_stone_double_slab"=>[1,238,238,238,238,238,238],"normal_stone_slab"=>[2,238,238,238,238,238,238],"normal_stone_stairs"=>[3,238,238,238,238,238,238],"noteblock"=>[1,312,312,312,312,312,312],"oak_double_slab"=>[1,54,54,54,54,54,54],"oak_fence"=>[4,54,54,54,54,54,54],"oak_leaves"=>[1,251,251,251,251,251,251],"oak_log"=>[1,277,277,278,278,278,278],"oak_planks"=>[1,54,54,54,54,54,54],"oak_shelf"=>[1,313,313,313,313,313,313],"oak_slab"=>[2,54,54,54,54,54,54],"oak_stairs"=>[3,54,54,54,54,54,54],"oak_wood"=>[1,278,278,278,278,278,278],"observer"=>[1,314,314,315,316,317,317],"obsidian"=>[1,318,318,318,318,318,318],"ochre_froglight"=>[1,319,319,320,320,320,320],"open_eyeblossom"=>[1,321,321,321,321,321,321],"orange_concrete"=>[1,322,322,322,322,322,322],"orange_concrete_powder"=>[1,323,323,323,323,323,323],"orange_glazed_terracotta"=>[1,324,324,324,324,324,324],"orange_stained_glass"=>[1,325,325,325,325,325,325],"orange_terracotta"=>[1,326,326,326,326,326,326],"orange_wool"=>[1,327,327,327,327,327,327],"oxidized_chiseled_copper"=>[1,328,328,328,328,328,328],"oxidized_copper"=>[1,329,329,329,329,329,329],"oxidized_copper_bulb"=>[1,330,330,330,330,330,330],"oxidized_copper_golem_statue"=>[1,329,329,329,329,329,329],"oxidized_copper_grate"=>[1,331,331,331,331,331,331],"oxidized_cut_copper"=>[1,332,332,332,332,332,332],"oxidized_cut_copper_slab"=>[2,332,332,332,332,332,332],"oxidized_cut_copper_stairs"=>[3,332,332,332,332,332,332],"packed_ice"=>[1,333,333,333,333,333,333],"packed_mud"=>[1,334,334,334,334,334,334],"pale_moss_block"=>[1,335,335,335,335,335,335],"pale_oak_double_slab"=>[1,336,336,336,336,336,336],"pale_oak_fence"=>[4,336,336,336,336,336,336],"pale_oak_leaves"=>[1,337,337,337,337,337,337],"pale_oak_log"=>[1,338,338,339,339,339,339],"pale_oak_planks"=>[1,336,336,336,336,336,336],"pale_oak_shelf"=>[1,340,340,340,340,340,340],"pale_oak_slab"=>[2,336,336,336,336,336,336],"pale_oak_stairs"=>[3,336,336,336,336,336,336],"pale_oak_wood"=>[1,339,339,339,339,339,339],"pearlescent_froglight"=>[1,341,341,342,342,342,342],"petrified_oak_double_slab"=>[1,54,54,54,54,54,54],"petrified_oak_slab"=>[2,54,54,54,54,54,54],"pink_concrete"=>[1,343,343,343,343,343,343],"pink_concrete_powder"=>[1,344,344,344,344,344,344],"pink_glazed_terracotta"=>[1,345,345,345,345,345,345],"pink_petals"=>[1,346,346,346,346,346,346],"pink_stained_glass"=>[1,347,347,347,347,347,347],"pink_terracotta"=>[1,348,348,348,348,348,348],"pink_wool"=>[1,349,349,349,349,349,349],"planks"=>[1,54,54,54,54,54,54],"podzol"=>[1,350,177,351,351,351,351],"polished_andesite"=>[1,352,352,352,352,352,352],"polished_andesite_double_slab"=>[1,352,352,352,352,352,352],"polished_andesite_slab"=>[2,352,352,352,352,352,352],"polished_andesite_stairs"=>[3,352,352,352,352,352,352],"polished_basalt"=>[1,353,353,354,354,354,354],"polished_blackstone"=>[1,355,355,355,355,355,355],"polished_blackstone_brick_double_slab"=>[1,356,356,356,356,356,356],"polished_blackstone_brick_slab"=>[2,356,356,356,356,356,356],"polished_blackstone_brick_stairs"=>[3,356,356,356,356,356,356],"polished_blackstone_bricks"=>[1,356,356,356,356,356,356],"polished_blackstone_double_slab"=>[1,355,355,355,355,355,355],"polished_blackstone_slab"=>[2,355,355,355,355,355,355],"polished_blackstone_stairs"=>[3,355,355,355,355,355,355],"polished_cinnabar"=>[1,357,357,357,357,357,357],"polished_cinnabar_double_slab"=>[1,357,357,357,357,357,357],"polished_cinnabar_slab"=>[2,357,357,357,357,357,357],"polished_cinnabar_stairs"=>[3,357,357,357,357,357,357],"polished_deepslate"=>[1,358,358,358,358,358,358],"polished_deepslate_double_slab"=>[1,358,358,358,358,358,358],"polished_deepslate_slab"=>[2,358,358,358,358,358,358],"polished_deepslate_stairs"=>[3,358,358,358,358,358,358],"polished_diorite"=>[1,359,359,359,359,359,359],"polished_diorite_double_slab"=>[1,359,359,359,359,359,359],"polished_diorite_slab"=>[2,359,359,359,359,359,359],"polished_diorite_stairs"=>[3,359,359,359,359,359,359],"polished_granite"=>[1,360,360,360,360,360,360],"polished_granite_double_slab"=>[1,360,360,360,360,360,360],"polished_granite_slab"=>[2,360,360,360,360,360,360],"polished_granite_stairs"=>[3,360,360,360,360,360,360],"polished_sulfur"=>[1,361,361,361,361,361,361],"polished_sulfur_double_slab"=>[1,361,361,361,361,361,361],"polished_sulfur_slab"=>[2,361,361,361,361,361,361],"polished_sulfur_stairs"=>[3,361,361,361,361,361,361],"polished_tuff"=>[1,362,362,362,362,362,362],"polished_tuff_double_slab"=>[1,362,362,362,362,362,362],"polished_tuff_slab"=>[2,362,362,362,362,362,362],"polished_tuff_stairs"=>[3,362,362,362,362,362,362],"powder_snow"=>[1,363,363,363,363,363,363],"prismarine"=>[1,364,364,364,364,364,364],"prismarine_brick_double_slab"=>[1,365,365,365,365,365,365],"prismarine_brick_slab"=>[2,365,365,365,365,365,365],"prismarine_bricks"=>[1,365,365,365,365,365,365],"prismarine_bricks_stairs"=>[3,365,365,365,365,365,365],"prismarine_double_slab"=>[1,364,364,364,364,364,364],"prismarine_slab"=>[2,364,364,364,364,364,364],"pumpkin"=>[1,74,74,75,76,75,75],"purple_concrete"=>[1,366,366,366,366,366,366],"purple_concrete_powder"=>[1,367,367,367,367,367,367],"purple_glazed_terracotta"=>[1,368,368,368,368,368,368],"purple_stained_glass"=>[1,369,369,369,369,369,369],"purple_terracotta"=>[1,370,370,370,370,370,370],"purple_wool"=>[1,371,371,371,371,371,371],"purpur_block"=>[1,173,173,173,173,173,173],"purpur_double_slab"=>[1,173,173,173,173,173,173],"purpur_pillar"=>[1,372,372,373,373,373,373],"purpur_slab"=>[2,173,173,173,173,173,173],"purpur_stairs"=>[3,173,173,173,173,173,173],"quartz_block"=>[1,374,374,375,375,375,375],"quartz_bricks"=>[1,376,376,376,376,376,376],"quartz_double_slab"=>[1,374,374,375,375,375,375],"quartz_ore"=>[1,377,377,377,377,377,377],"quartz_pillar"=>[1,378,378,379,379,379,379],"quartz_slab"=>[2,374,374,375,375,375,375],"quartz_stairs"=>[3,374,374,375,375,375,375],"raw_copper_block"=>[1,380,380,380,380,380,380],"raw_gold_block"=>[1,381,381,381,381,381,381],"raw_iron_block"=>[1,382,382,382,382,382,382],"red_concrete"=>[1,383,383,383,383,383,383],"red_concrete_powder"=>[1,384,384,384,384,384,384],"red_glazed_terracotta"=>[1,385,385,385,385,385,385],"red_mushroom_block"=>[1,62,62,62,62,62,62],"red_nether_brick"=>[1,386,386,386,386,386,386],"red_nether_brick_double_slab"=>[1,386,386,386,386,386,386],"red_nether_brick_slab"=>[2,386,386,386,386,386,386],"red_nether_brick_stairs"=>[3,386,386,386,386,386,386],"red_sand"=>[1,387,387,387,387,387,387],"red_sandstone"=>[1,92,388,389,389,389,389],"red_sandstone_double_slab"=>[1,92,388,389,389,389,389],"red_sandstone_slab"=>[2,92,388,389,389,389,389],"red_sandstone_stairs"=>[3,92,388,389,389,389,389],"red_stained_glass"=>[1,390,390,390,390,390,390],"red_terracotta"=>[1,391,391,391,391,391,391],"red_wool"=>[1,392,392,392,392,392,392],"redstone_block"=>[1,393,393,393,393,393,393],"redstone_lamp"=>[1,394,394,394,394,394,394],"redstone_ore"=>[1,271,271,271,271,271,271],"reinforced_deepslate"=>[1,395,396,397,397,397,397],"resin_block"=>[1,398,398,398,398,398,398],"resin_brick_double_slab"=>[1,399,399,399,399,399,399],"resin_brick_slab"=>[2,399,399,399,399,399,399],"resin_brick_stairs"=>[3,399,399,399,399,399,399],"resin_bricks"=>[1,399,399,399,399,399,399],"sand"=>[1,400,400,400,400,400,400],"sandstone"=>[1,95,401,402,402,402,402],"sandstone_double_slab"=>[1,95,401,402,402,402,402],"sandstone_slab"=>[2,95,401,402,402,402,402],"sandstone_stairs"=>[3,95,401,402,402,402,402],"shroomlight"=>[1,403,403,403,403,403,403],"silver_glazed_terracotta"=>[1,404,404,404,404,404,404],"slime"=>[1,405,405,405,405,405,405],"small_dripleaf_block"=>[1,406,407,408,409,409,409],"smithing_table"=>[1,410,411,412,412,413,413],"smoker"=>[1,272,273,274,414,274,274],"smooth_basalt"=>[1,415,415,415,415,415,415],"smooth_quartz"=>[1,416,416,416,416,416,416],"smooth_quartz_double_slab"=>[1,416,416,416,416,416,416],"smooth_quartz_slab"=>[2,416,416,416,416,416,416],"smooth_quartz_stairs"=>[3,416,416,416,416,416,416],"smooth_red_sandstone"=>[1,92,92,92,92,92,92],"smooth_red_sandstone_double_slab"=>[1,92,92,92,92,92,92],"smooth_red_sandstone_slab"=>[2,92,92,92,92,92,92],"smooth_red_sandstone_stairs"=>[3,92,92,92,92,92,92],"smooth_sandstone"=>[1,95,95,95,95,95,95],"smooth_sandstone_double_slab"=>[1,95,95,95,95,95,95],"smooth_sandstone_slab"=>[2,95,95,95,95,95,95],"smooth_sandstone_stairs"=>[3,95,95,95,95,95,95],"smooth_stone"=>[1,417,417,417,417,417,417],"smooth_stone_double_slab"=>[1,417,417,418,418,418,418],"smooth_stone_slab"=>[2,417,417,418,418,418,418],"snow"=>[1,419,419,419,419,419,419],"soul_sand"=>[1,420,420,420,420,420,420],"soul_soil"=>[1,421,421,421,421,421,421],"sponge"=>[1,422,422,422,422,422,422],"spruce_double_slab"=>[1,423,423,423,423,423,423],"spruce_fence"=>[4,423,423,423,423,423,423],"spruce_leaves"=>[1,424,424,424,424,424,424],"spruce_log"=>[1,425,425,426,426,426,426],"spruce_planks"=>[1,423,423,423,423,423,423],"spruce_shelf"=>[1,427,427,427,427,427,427],"spruce_slab"=>[2,423,423,423,423,423,423],"spruce_stairs"=>[3,423,423,423,423,423,423],"spruce_wood"=>[1,426,426,426,426,426,426],"stained_glass"=>[1,428,428,428,428,428,428],"stained_hardened_clay"=>[1,429,429,429,429,429,429],"stickyPistonArmCollision"=>[1,430,430,430,430,430,430],"stone"=>[1,238,238,238,238,238,238],"stone_brick_double_slab"=>[1,239,239,239,239,239,239],"stone_brick_slab"=>[2,239,239,239,239,239,239],"stone_brick_stairs"=>[3,239,239,239,239,239,239],"stone_bricks"=>[1,239,239,239,239,239,239],"stone_slab"=>[2,417,417,418,418,418,418],"stone_stairs"=>[3,111,111,111,111,111,111],"stonebrick"=>[1,239,239,239,239,239,239],"stripped_bamboo_block"=>[1,434,434,435,435,435,435],"stripped_crimson_hyphae"=>[1,436,436,436,436,436,436],"stripped_crimson_stem"=>[1,437,437,436,436,436,436],"stripped_warped_hyphae"=>[1,438,438,438,438,438,438],"stripped_warped_stem"=>[1,439,439,438,438,438,438],"structure_block"=>[1,440,440,440,440,440,440],"sulfur"=>[1,441,441,441,441,441,441],"sulfur_brick_double_slab"=>[1,442,442,442,442,442,442],"sulfur_brick_slab"=>[2,442,442,442,442,442,442],"sulfur_brick_stairs"=>[3,442,442,442,442,442,442],"sulfur_bricks"=>[1,442,442,442,442,442,442],"sulfur_double_slab"=>[1,441,441,441,441,441,441],"sulfur_slab"=>[2,441,441,441,441,441,441],"sulfur_spike"=>[1,443,444,445,446,443,447],"sulfur_stairs"=>[3,441,441,441,441,441,441],"suspicious_gravel"=>[1,448,448,448,448,448,448],"suspicious_sand"=>[1,449,449,449,449,449,449],"target"=>[1,450,450,451,451,451,451],"tinted_glass"=>[1,452,452,452,452,452,452],"tnt"=>[1,453,454,455,455,455,455],"tube_coral_block"=>[1,118,118,118,118,118,118],"tuff"=>[1,456,456,456,456,456,456],"tuff_brick_double_slab"=>[1,457,457,457,457,457,457],"tuff_brick_slab"=>[2,457,457,457,457,457,457],"tuff_brick_stairs"=>[3,457,457,457,457,457,457],"tuff_bricks"=>[1,457,457,457,457,457,457],"tuff_double_slab"=>[1,456,456,456,456,456,456],"tuff_slab"=>[2,456,456,456,456,456,456],"tuff_stairs"=>[3,456,456,456,456,456,456],"verdant_froglight"=>[1,458,458,459,459,459,459],"warped_double_slab"=>[1,460,460,460,460,460,460],"warped_fence"=>[4,460,460,460,460,460,460],"warped_nylium"=>[1,461,137,462,462,462,462],"warped_planks"=>[1,460,460,460,460,460,460],"warped_shelf"=>[1,463,463,463,463,463,463],"warped_slab"=>[2,460,460,460,460,460,460],"warped_stairs"=>[3,460,460,460,460,460,460],"warped_wart_block"=>[1,465,465,465,465,465,465],"waxed_chiseled_copper"=>[1,86,86,86,86,86,86],"waxed_copper"=>[1,114,114,114,114,114,114],"waxed_copper_bulb"=>[1,115,115,115,115,115,115],"waxed_copper_golem_statue"=>[1,114,114,114,114,114,114],"waxed_copper_grate"=>[1,116,116,116,116,116,116],"waxed_cut_copper"=>[1,142,142,142,142,142,142],"waxed_cut_copper_slab"=>[2,142,142,142,142,142,142],"waxed_cut_copper_stairs"=>[3,142,142,142,142,142,142],"waxed_exposed_chiseled_copper"=>[1,192,192,192,192,192,192],"waxed_exposed_copper"=>[1,193,193,193,193,193,193],"waxed_exposed_copper_bulb"=>[1,194,194,194,194,194,194],"waxed_exposed_copper_golem_statue"=>[1,193,193,193,193,193,193],"waxed_exposed_copper_grate"=>[1,195,195,195,195,195,195],"waxed_exposed_cut_copper"=>[1,196,196,196,196,196,196],"waxed_exposed_cut_copper_slab"=>[2,196,196,196,196,196,196],"waxed_exposed_cut_copper_stairs"=>[3,196,196,196,196,196,196],"waxed_oxidized_chiseled_copper"=>[1,328,328,328,328,328,328],"waxed_oxidized_copper"=>[1,329,329,329,329,329,329],"waxed_oxidized_copper_bulb"=>[1,330,330,330,330,330,330],"waxed_oxidized_copper_golem_statue"=>[1,329,329,329,329,329,329],"waxed_oxidized_copper_grate"=>[1,331,331,331,331,331,331],"waxed_oxidized_cut_copper"=>[1,332,332,332,332,332,332],"waxed_oxidized_cut_copper_slab"=>[2,332,332,332,332,332,332],"waxed_oxidized_cut_copper_stairs"=>[3,332,332,332,332,332,332],"waxed_weathered_chiseled_copper"=>[1,466,466,466,466,466,466],"waxed_weathered_copper"=>[1,467,467,467,467,467,467],"waxed_weathered_copper_bulb"=>[1,468,468,468,468,468,468],"waxed_weathered_copper_golem_statue"=>[1,467,467,467,467,467,467],"waxed_weathered_copper_grate"=>[1,469,469,469,469,469,469],"waxed_weathered_cut_copper"=>[1,470,470,470,470,470,470],"waxed_weathered_cut_copper_slab"=>[2,470,470,470,470,470,470],"waxed_weathered_cut_copper_stairs"=>[3,470,470,470,470,470,470],"weathered_chiseled_copper"=>[1,466,466,466,466,466,466],"weathered_copper"=>[1,467,467,467,467,467,467],"weathered_copper_bulb"=>[1,468,468,468,468,468,468],"weathered_copper_golem_statue"=>[1,467,467,467,467,467,467],"weathered_copper_grate"=>[1,469,469,469,469,469,469],"weathered_cut_copper"=>[1,470,470,470,470,470,470],"weathered_cut_copper_slab"=>[2,470,470,470,470,470,470],"weathered_cut_copper_stairs"=>[3,470,470,470,470,470,470],"wet_sponge"=>[1,471,471,471,471,471,471],"white_concrete"=>[1,112,112,112,112,112,112],"white_concrete_powder"=>[1,113,113,113,113,113,113],"white_glazed_terracotta"=>[1,472,472,472,472,472,472],"white_stained_glass"=>[1,428,428,428,428,428,428],"white_terracotta"=>[1,429,429,429,429,429,429],"white_wool"=>[1,473,473,473,473,473,473],"wood"=>[1,278,278,278,278,278,278],"wooden_slab"=>[2,54,54,54,54,54,54],"wool"=>[1,473,473,473,473,473,473],"yellow_concrete"=>[1,474,474,474,474,474,474],"yellow_concrete_powder"=>[1,475,475,475,475,475,475],"yellow_glazed_terracotta"=>[1,476,476,476,476,476,476],"yellow_stained_glass"=>[1,477,477,477,477,477,477],"yellow_terracotta"=>[1,478,478,478,478,478,478],"yellow_wool"=>[1,479,479,479,479,479,479]];
    /** @var array<string,string> */
    public const ALIASES = ["grass_block"=>"grass","dirt_path"=>"grass_path","snow_block"=>"snow","note_block"=>"noteblock","sea_lantern"=>"seaLantern","bricks"=>"brick_block","terracotta"=>"hardened_clay","nether_bricks"=>"nether_brick","red_nether_bricks"=>"red_nether_brick","magma_block"=>"magma","slime_block"=>"slime","jack_o_lantern"=>"lit_pumpkin","melon"=>"melon_block","stone_bricks"=>"stonebrick","smooth_stone"=>"smooth_stone","quartz_bricks"=>"quartz_bricks","mob_spawner"=>"mob_spawner","spawner"=>"mob_spawner","end_stone_bricks"=>"end_bricks","prismarine_bricks"=>"prismarine","dark_prismarine"=>"prismarine","cobweb"=>"web","oak_wood"=>"wood"];
    public const SIZES = [0.0625,0.125,0.1875,0.25,0.3125,0.375,0.4375,0.5,0.5625,0.625,0.6875,0.75,0.8125,0.875,0.9375,1.0,1.0625,1.125,1.1875,1.25,1.3125,1.375,1.4375,1.5,1.5625,1.625,1.6875,1.75,1.8125,1.875,1.9375,2.0,2.25,2.5,2.75,3,3.25,3.5,3.75,4,4.5,5,6,7,8,10,12,16];
    public static function canonical(string $id): ?string{
        $raw=strtolower(trim($id));
        $raw=preg_replace('/^minecraft:/','',$raw) ?? $raw;
        $candidates=[$raw,self::ALIASES[$raw]??null,preg_replace('/_block$/','',$raw)??$raw,str_replace('_','',$raw)];
        $camel=preg_replace_callback('/_([a-z])/',fn($m)=>strtoupper($m[1]),$raw)??$raw;
        $candidates[]=$camel;
        foreach($candidates as $c){
            if(is_string($c) && isset(self::BLOCKS[$c])) return $c;
        }
        return null;
    }
    public static function row(string $id): ?array{
        $c=self::canonical($id); return $c===null?null:self::BLOCKS[$c];
    }
    public static function variantCount(int $shape): int{
        return match($shape){1 => 1, 2 => 2, 3 => 6, 4 => 6, default => 1};
    }
    public static function sizeIndex(float $size): int{
        $best=0;$d=INF;
        foreach(self::SIZES as $i=>$v){$x=abs($v-$size); if($x<$d){$d=$x;$best=$i;}}
        return $best;
    }
}

final class DisplayMath{
    public static function clamp(float $v,float $min,float $max): float{return max($min,min($max,$v));}
    public static function add(Vector3 $a, Vector3 $b): Vector3{return new Vector3($a->x+$b->x,$a->y+$b->y,$a->z+$b->z);}
    public static function sub(Vector3 $a, Vector3 $b): Vector3{return new Vector3($a->x-$b->x,$a->y-$b->y,$a->z-$b->z);}
    public static function mul(Vector3 $a,float $n): Vector3{return new Vector3($a->x*$n,$a->y*$n,$a->z*$n);}
    public static function dot(Vector3 $a,Vector3 $b):float{return $a->x*$b->x+$a->y*$b->y+$a->z*$b->z;}
    public static function cross(Vector3 $a,Vector3 $b):Vector3{return new Vector3($a->y*$b->z-$a->z*$b->y,$a->z*$b->x-$a->x*$b->z,$a->x*$b->y-$a->y*$b->x);}
    public static function length(Vector3 $a):float{return sqrt(self::dot($a,$a));}
    public static function unit(Vector3 $a):Vector3{$l=self::length($a);return $l<1.0e-8?new Vector3(0,0,0):self::mul($a,1/$l);}
    public static function center(Entity $e): Vector3{return $e->getPosition()->add(0,$e->getSize()->getHeight()*$e->getScale()/2,0);}
    public static function rotateLocal(Vector3 $v,float $yaw,float $pitch,float $roll):Vector3{
        $y=deg2rad($yaw);$p=deg2rad($pitch);$r=deg2rad($roll);
        $cy=cos($y);$sy=sin($y);$cp=cos($p);$sp=sin($p);$cr=cos($r);$sr=sin($r);
        // Preserve BlockDisplay's Y -> X -> Z rotation order.
        $x1=$v->x*$cy+$v->z*$sy;$z1=-$v->x*$sy+$v->z*$cy;$y1=$v->y;
        $y2=$y1*$cp-$z1*$sp;$z2=$y1*$sp+$z1*$cp;$x2=$x1;
        return new Vector3($x2*$cr-$y2*$sr,$x2*$sr+$y2*$cr,$z2);
    }
}

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

class ModelFaceEntity extends BaseBDEntity implements \pocketmine\entity\NeverSavedWithChunkEntity{
    protected function getInitialSizeInfo(): EntitySizeInfo{return new EntitySizeInfo(0.01,0.01);}
    protected static string $NETWORK='bd:model_face'; protected int $texture=0; protected int $shape=1; protected int $shapeVariant=0; protected int $move=0; protected string $face='up'; public int $ownerDisplayId=-1;
    public static function getNetworkTypeId():string{return static::$NETWORK;}
    public function configure(int $texture,int $shape,int $variant,string $face,float $scale,float $yaw,float $pitch,float $roll,int $ownerId,int $move=0):void{$this->texture=max(0,min(479,$texture));$this->shape=$shape;$this->shapeVariant=$variant;$this->face=$face;$this->ownerDisplayId=$ownerId;$this->move=max(0,min(6,$move));$this->setScale($scale);$this->setRotation($yaw,$pitch);$this->setRoll($roll);$this->networkPropertiesDirty=true;}
    public function getTexture():int{return $this->texture;}
    public function saveNBT():CompoundTag{ $n=parent::saveNBT(); return $n->setInt('BDTexture',$this->texture)->setInt('BDShape',$this->shape)->setInt('BDShapeVariant',$this->shapeVariant)->setInt('BDMove',$this->move)->setString('BDFace',$this->face)->setInt('BDOwnerDisplay',$this->ownerDisplayId); }
    protected function initEntity(CompoundTag $nbt):void{parent::initEntity($nbt);$this->texture=bdReadIntTag($nbt,'BDTexture',0);$this->shape=bdReadIntTag($nbt,'BDShape',1);$this->shapeVariant=bdReadIntTag($nbt,'BDShapeVariant',0);$this->move=bdReadIntTag($nbt,'BDMove',0);$this->face=$nbt->getString('BDFace','up');$this->ownerDisplayId=bdReadIntTag($nbt,'BDOwnerDisplay',-1);$this->networkPropertiesDirty=true;}
    protected function syncNetworkData(EntityMetadataCollection $properties):void{parent::syncNetworkData($properties);$properties->setInt(EntityMetadataProperties::VARIANT,$this->texture);$properties->setInt(EntityMetadataProperties::MARK_VARIANT,$this->move);$properties->setInt(EntityMetadataProperties::SKIN_ID,$this->shape*10+$this->shapeVariant);$properties->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::HAS_COLLISION,false);}
}

class HandleEntity extends BaseBDEntity implements \pocketmine\entity\NeverSavedWithChunkEntity{
    protected static string $NETWORK='bd:handle'; public int $kind=0; public int $color=0; public bool $hot=false; public int $axis=0; public int $sign=1; public ?int $ownerDisplayId=null;
    protected function getInitialSizeInfo(): EntitySizeInfo{return new EntitySizeInfo(0.4,0.4);}
    public static function getNetworkTypeId():string{return static::$NETWORK;}
    protected function syncNetworkData(EntityMetadataCollection $properties):void{parent::syncNetworkData($properties);$properties->setInt(EntityMetadataProperties::VARIANT,$this->kind);$properties->setInt(EntityMetadataProperties::MARK_VARIANT,$this->color);$properties->setInt(EntityMetadataProperties::SKIN_ID,$this->hot?1:0);$properties->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::HAS_COLLISION,false);}
    public function saveNBT():CompoundTag{$n=parent::saveNBT();return $n->setInt('BDKind',$this->kind)->setInt('BDColor',$this->color)->setByte('BDHot',$this->hot?1:0)->setInt('BDAxis',$this->axis)->setInt('BDSign',$this->sign)->setInt('BDOwner',$this->ownerDisplayId??-1);}
    protected function initEntity(CompoundTag $nbt):void{parent::initEntity($nbt);$this->kind=bdReadIntTag($nbt,'BDKind',0);$this->color=bdReadIntTag($nbt,'BDColor',0);$this->hot=$nbt->getByte('BDHot',0)!==0;$this->axis=bdReadIntTag($nbt,'BDAxis',0);$this->sign=bdReadIntTag($nbt,'BDSign',1);$o=bdReadIntTag($nbt,'BDOwner',-1);$this->ownerDisplayId=$o>=0?$o:null;}
    public function setHot(bool $v):void{$this->hot=$v;$this->networkPropertiesDirty=true;}
}

abstract class BDITem extends Item implements ItemComponents{
    use ItemComponentsTrait;
    protected function boot(string $texture):void{$this->initComponent($texture);$this->addComponent(new InteractButtonComponent('BDBlockDisplay'));$this->addComponent(new UseAnimationComponent(UseAnimationComponent::ANIMATION_NONE));}
    public function getMaxStackSize():int{return 1;}
}
final class WandItem extends BDITem{public function __construct(ItemIdentifier $i,string $n='BDBlockDisplay Wand'){parent::__construct($i,$n);$this->boot('bd_wand_move');}}
final class RotateWandItem extends BDITem{public function __construct(ItemIdentifier $i,string $n='BDBlockDisplay Rotate Wand'){parent::__construct($i,$n);$this->boot('bd_wand_rotate');}}
final class ScaleWandItem extends BDITem{public function __construct(ItemIdentifier $i,string $n='BDBlockDisplay Scale Wand'){parent::__construct($i,$n);$this->boot('bd_wand_scale');}}
final class GroupToolItem extends BDITem{public function __construct(ItemIdentifier $i,string $n='BDBlockDisplay Group Tool'){parent::__construct($i,$n);$this->boot('bd_group_tool');}}

final class SimpleForm implements \pocketmine\form\Form{
    public function __construct(private string $json,private \Closure $handler){}
    public function jsonSerialize():mixed{return json_decode($this->json,true);} public function handleResponse(Player $player,mixed $data):void{($this->handler)($player,$data);}
}

final class BlockDisplayManager{
    public const MODE_NAMES=['Off','Move','Rotate','Scale','Calibrate'];
    public const MOVE_STEP=1/16; public const ROT_STEP=15.0; public const SIZE_STEP=1/8;
    /** @var array<int,int[]> */ private array $handles=[];
    /** @var array<string,int> */ private array $selected=[];
    /** @var array<int,array<string,mixed>> */ private array $drags=[];
    /** @var array<int,array{displayId:int,last:Vector3}> */ private array $playerControls=[];
    /** @var array<int,array<int,bool>> */ private array $groupMembers=[];
    /** @var array<int,array<string,array{0:int,1:int,2:int}>> */ private array $clientSolidCells=[];
    /** @var array<int,int> Player -> Altay World::getId() for the client collision snapshot. */ private array $clientSolidWorld=[];
    /** @var array<int,string> Player -> current client collision anchor chunk. */ private array $clientSolidChunk=[];
    private const CLIENT_COLLISION_RADIUS=64.0;
    private const MAX_CLIENT_SOLID_CELLS=4096;
    public function __construct(private main $plugin){}

    public function select(Player $p,DisplayEntity $d):void{
        $oldId=$this->selected[$p->getName()]??null;
        if($oldId!==null && $oldId!==$d->getId()){
            $this->stopPlayerControl($p,false); $this->stopDrag($p); $this->removeHandles($p);
            foreach($p->getWorld()->getEntities() as $e){if($e instanceof DisplayEntity && $e->getId()===$oldId){$e->setInvisible(true);break;}}
        }
        $this->selected[$p->getName()]=$d->getId();$d->setInvisible(false);$this->setMode($d,1);$this->refreshHandles($p);$p->sendMessage('§aSelected BlockDisplay #'.$d->getId().' §7('.$d->getBlockId().')');$d->spawnTo($p);
    }
    public function selected(Player $p):?DisplayEntity{
        $id=$this->selected[$p->getName()]??null;if($id===null)return null;
        foreach($p->getWorld()->getEntities() as $e)if($e instanceof DisplayEntity && $e->getId()===$id && !$e->isClosed())return $e;
        return null;
    }
    public function clearSelection(Player $p):void{
        $this->stopPlayerControl($p,false);$this->stopDrag($p);$this->removeHandles($p);unset($this->groupMembers[$p->getId()]);$id=$this->selected[$p->getName()]??null;unset($this->selected[$p->getName()]);
        if($id!==null && !in_array($id,$this->selected,true)){foreach($p->getWorld()->getEntities() as $e)if($e instanceof DisplayEntity && $e->getId()===$id){$e->setInvisible(true);break;}}
    }
    public function toggleGroupMember(Player $p, DisplayEntity $d): void{
        $pid=$p->getId();
        $this->groupMembers[$pid] ??= [];
        if(isset($this->groupMembers[$pid][$d->getId()])){
            unset($this->groupMembers[$pid][$d->getId()]);
            $p->sendMessage('§7Removed §f#'.$d->getId().' §7from group. §8('.$this->groupCount($p).')');
        }else{
            $this->groupMembers[$pid][$d->getId()]=true;
            $this->selected[$p->getName()]=$d->getId();
            $d->setInvisible(false);
            $p->sendMessage('§aAdded §f#'.$d->getId().' §7'.$d->getBlockId().' to group. §8('.$this->groupCount($p).')');
        }
        $this->groupPrune($p);
    }
    public function clearGroup(Player $p): void{
        unset($this->groupMembers[$p->getId()]);
        $p->sendMessage('§7Group cleared.');
    }
    public function groupCount(Player $p): int{
        $this->groupPrune($p);
        return count($this->groupMembers[$p->getId()] ?? []);
    }
    /** @return DisplayEntity[] */
    public function groupDisplays(Player $p): array{
        $this->groupPrune($p);
        $out=[];
        foreach(array_keys($this->groupMembers[$p->getId()] ?? []) as $id){
            $d=$this->displayById($p,(int)$id);
            if($d!==null)$out[]=$d;
        }
        return $out;
    }
    private function groupPrune(Player $p): void{
        $pid=$p->getId();
        if(!isset($this->groupMembers[$pid]))return;
        foreach(array_keys($this->groupMembers[$pid]) as $id){
            if($this->displayById($p,(int)$id)===null)unset($this->groupMembers[$pid][$id]);
        }
        if($this->groupMembers[$pid]===[])unset($this->groupMembers[$pid]);
    }
    public function selectAllNearbyGroup(Player $p,float $radius=16.0): int{
        $pid=$p->getId();$this->groupMembers[$pid]=[];$pp=$p->getPosition();$r2=$radius*$radius;
        foreach($p->getWorld()->getEntities() as $e){
            if(!$e instanceof DisplayEntity || $e->isClosed())continue;
            if($e->getPosition()->distanceSquared($pp)<=$r2){
                $this->groupMembers[$pid][$e->getId()]=true;$e->setInvisible(false);
            }
        }
        $n=$this->groupCount($p);
        $p->sendMessage('§aSelected §f'.$n.' §adisplays in a '.$radius.' block radius.');
        return $n;
    }
    private function groupCenter(Player $p): ?Vector3{
        $ds=$this->groupDisplays($p);if($ds===[])return null;
        $minX=$minY=$minZ=INF;$maxX=$maxY=$maxZ=-INF;
        foreach($ds as $d){$l=$d->getLocation();$s=$d->getDisplaySize();$minX=min($minX,$l->x-$s/2);$maxX=max($maxX,$l->x+$s/2);$minY=min($minY,$l->y);$maxY=max($maxY,$l->y+$s);$minZ=min($minZ,$l->z-$s/2);$maxZ=max($maxZ,$l->z+$s/2);}
        return new Vector3(($minX+$maxX)/2,($minY+$maxY)/2,($minZ+$maxZ)/2);
    }
    public function moveGroup(Player $p,float $dx,float $dy,float $dz): void{
        foreach($this->groupDisplays($p) as $d){$l=$d->getLocation();$d->teleport(new Location($l->x+$dx,$l->y+$dy,$l->z+$dz,$d->getWorld(),$l->yaw,$l->pitch));$this->syncModel($d);}
    }
    public function rotateGroup(Player $p,int $axis,float $delta): void{
        $center=$this->groupCenter($p);if($center===null)return;$rad=deg2rad($delta);$c=cos($rad);$s=sin($rad);
        foreach($this->groupDisplays($p) as $d){
            $l=$d->getLocation();$v=new Vector3($l->x-$center->x,$l->y-$center->y,$l->z-$center->z);
            if($axis===0){$ny=$v->y*$c-$v->z*$s;$nz=$v->y*$s+$v->z*$c;$v=new Vector3($v->x,$ny,$nz);}
            elseif($axis===1){$nx=$v->x*$c+$v->z*$s;$nz=-$v->x*$s+$v->z*$c;$v=new Vector3($nx,$v->y,$nz);}
            else{$nx=$v->x*$c-$v->y*$s;$ny=$v->x*$s+$v->y*$c;$v=new Vector3($nx,$ny,$v->z);}
            $d->teleport(new Location($center->x+$v->x,$center->y+$v->y,$center->z+$v->z,$d->getWorld(),$l->yaw,$l->pitch));
            $this->rotateAxis($d,$axis,$delta);$this->syncModel($d);
        }
    }
    public function scaleGroup(Player $p,float $factor): void{
        $center=$this->groupCenter($p);if($center===null)return;
        foreach($this->groupDisplays($p) as $d){
            $l=$d->getLocation();$v=new Vector3(($l->x-$center->x)*$factor,($l->y-$center->y)*$factor,($l->z-$center->z)*$factor);
            $d->teleport(new Location($center->x+$v->x,$center->y+$v->y,$center->z+$v->z,$d->getWorld(),$l->yaw,$l->pitch));$d->setDisplaySize($d->getDisplaySize()*$factor);$this->syncModel($d);
        }
    }
    public function duplicateGroup(Player $p): int{
        $ds=$this->groupDisplays($p);if($ds===[]){$p->sendMessage('§cGroup is empty.');return 0;}
        $offset=0.0;foreach($ds as $d)$offset=max($offset,$d->getDisplaySize());$offset=max(1.0,$offset+1.0);
        $new=[];
        foreach($ds as $d){
            $l=$d->getLocation();$n=new DisplayEntity(new Location($l->x+$offset,$l->y,$l->z,$d->getWorld(),$l->yaw,$l->pitch),null);
            $n->setBlockId($d->getBlockId());$n->setDisplaySize($d->getDisplaySize());$n->setRoll($d->getRoll());$n->setSolid($d->isSolid());$n->setVariant($d->getVariant());$n->spawnToAll();$this->syncModel($n);$new[]=$n->getId();
        }
        $this->groupMembers[$p->getId()]=array_fill_keys($new,true);
        $p->sendMessage('§aDuplicated group: §f'.count($new).' §adisplays.');return count($new);
    }

    public function deleteGroup(Player $p): int{
        $n=0;foreach($this->groupDisplays($p) as $d){$this->deleteDisplay($d);$n++;}$this->clearGroup($p);return $n;
    }

    public function setMode(DisplayEntity $d,int $mode):void{
        $d->setMode($mode);$d->syncModelMetadata();
        foreach($this->drags as $pid=>$drag){if(($drag['displayId']??-1)===$d->getId())unset($this->drags[$pid]);}
        foreach($this->playerControls as $pid=>$control){if(($control['displayId']??-1)===$d->getId())unset($this->playerControls[$pid]);}
    }
    public function findInSight(Player $p,array $classes,int $max=48):?Entity{
        $o=$p->getEyePos();$dir=$p->getDirectionVector();$best=null;$bestT=$max+1;
        foreach($p->getWorld()->getEntities() as $e){
            if($e->isClosed()||$e->getId()===$p->getId())continue;$ok=false;foreach($classes as $c)if($e instanceof $c){$ok=true;break;}if(!$ok)continue;
            $v=$e->getPosition()->subtractVector($o);$t=$v->dot($dir);if($t<0||$t>$max||$t>=$bestT)continue;$closest=$o->addVector($dir->multiply($t));$r=$closest->distance($e->getPosition());
            $allow=max(.65,$e->getSize()->getWidth()*$e->getScale()/2+.35);if($r<=$allow){$best=$e;$bestT=$t;}
        }
        return $best;
    }
    private function handleDefinitions(DisplayEntity $d):array{
        $s=max(.25,$d->getDisplaySize());$reach=max(.72,$s*.5+.38);$c=$this->editorCenter($d);$r=$s*.62;
        return [
            ['xp',$c->add($reach,0,0),0,0,1],['xm',$c->add(-$reach,0,0),0,0,-1],
            ['yp',$c->add(0,$reach,0),1,1,1],['ym',$c->add(0,-$reach,0),2,1,-1],
            ['zp',$c->add(0,0,$reach),0,2,1],['zm',$c->add(0,0,-$reach),0,2,-1],
            ['rx',$c->add($r,0,0),3,0,1],['ry',$c->add(0,$r,0),3,1,1],['rz',$c->add(0,0,$r),3,2,1],
            ['sc',$c->add($r*.9,$r*.9,$r*.9),4,3,1],
        ];
    }
    private function editorCenter(DisplayEntity $d):Vector3{return $d->getPosition()->add(0,$d->getDisplaySize()/2,0);}
    public function refreshHandles(Player $p):void{
        $d=$this->selected($p);if($d===null){$this->removeHandles($p);return;}$this->removeHandles($p);$mode=$d->getMode();$items=[];
        foreach($this->handleDefinitions($d) as $it){
            $show=($mode===1&&$it[2]<=2)||($mode===2&&$it[2]===3)||($mode===3&&$it[2]===4);if(!$show)continue;
            [$name,$pos,$kind,$axis,$sign]=$it;$h=$this->newHandle($p->getWorld(),$pos,$kind,$axis,$sign,$d->getId());$h->spawnTo($p);$items[]=$h->getId();
        }
        $this->handles[$p->getId()]=$items;
    }
    private function newHandle(World $w,Vector3 $pos,int $kind,int $axis,int $sign,int $owner):HandleEntity{
        $h=new HandleEntity(new Location($pos->x,$pos->y,$pos->z,$w,0.0,0.0),null);$h->kind=$kind;$h->axis=$axis;$h->sign=$sign;$h->ownerDisplayId=$owner;$h->color=$kind===4?3:$axis;$h->setScale(1.0);
        if($kind===0){$yaw=$axis===0?($sign>0?-90.0:90.0):($axis===2?($sign>0?180.0:0.0):0.0);$h->setRotation($yaw,0.0);}
        elseif($kind===1){$h->setRotation(0.0,$sign>0?0.0:180.0);}
        return $h;
    }
    public function removeHandles(Player $p):void{
        $ids=$this->handles[$p->getId()]??[];if($ids!==[]){foreach($this->plugin->getServer()->getWorldManager()->getWorlds() as $w)foreach($w->getEntities() as $e)if($e instanceof HandleEntity&&in_array($e->getId(),$ids,true))$e->flagForDespawn();}unset($this->handles[$p->getId()]);
    }
    public function updateHandlePositions(Player $p):void{
        $d=$this->selected($p);if($d===null)return;$wanted=[];foreach($this->handleDefinitions($d) as $it)$wanted[$it[0]]=$it[1];
        foreach($p->getWorld()->getEntities() as $e){if(!$e instanceof HandleEntity||$e->ownerDisplayId!==$d->getId())continue;$key=$this->handleKey($e);if($key!==null&&isset($wanted[$key]))$e->teleport(new Location($wanted[$key]->x,$wanted[$key]->y,$wanted[$key]->z,$p->getWorld(),$e->getLocation()->yaw,$e->getLocation()->pitch));}
    }
    private function handleKey(HandleEntity $h):?string{
        if($h->kind===0)return $h->axis===0?($h->sign>0?'xp':'xm'):($h->axis===2?($h->sign>0?'zp':'zm'):null);
        if($h->kind===1)return $h->sign>0?'yp':'ym';
        if($h->kind===2)return 'ym';
        if($h->kind===3)return ['rx','ry','rz'][$h->axis]??null;
        if($h->kind===4)return 'sc';return null;
    }
    public function findOwnerDisplay(ModelFaceEntity $m):?DisplayEntity{foreach($m->getWorld()->getEntities() as $e)if($e instanceof DisplayEntity&&$e->getId()===$m->ownerDisplayId&&!$e->isClosed())return $e;return null;}
    public function clearAllHandles():void{
        $this->playerControls=[];
        foreach($this->handles as $playerId=>$ids){foreach($this->plugin->getServer()->getWorldManager()->getWorlds() as $w)foreach($w->getEntities() as $e)if($e instanceof HandleEntity&&in_array($e->getId(),$ids,true))$e->flagForDespawn();} $this->handles=[];$this->drags=[];}
    public function listDisplays(Player $p):array{$a=[];foreach($p->getWorld()->getEntities() as $e)if($e instanceof DisplayEntity&&!$e->isClosed())$a[]=$e;return $a;}

    public function startPlayerControl(Player $p,DisplayEntity $d):void{
        $this->stopDrag($p);
        $this->removeHandles($p);
        $this->selected[$p->getName()]=$d->getId();
        $d->setInvisible(false);
        $d->setMode(1);
        $this->playerControls[$p->getId()]=['displayId'=>$d->getId(),'last'=>$p->getPosition()->asVector3()];
        $this->syncModel($d,0);
        $p->sendMessage('§aPlayer Control §fACTIVE§a. Walk, strafe, jump or sneak to move the block. Use the menu again to stop.');
    }

    public function stopPlayerControl(Player $p,bool $message=true):void{
        if(isset($this->playerControls[$p->getId()])){
            unset($this->playerControls[$p->getId()]);
            if($message)$p->sendMessage('§7Player Control stopped.');
        }
    }

    public function hasPlayerControl(Player $p):bool{return isset($this->playerControls[$p->getId()]);}

    public function getPlayerControlDisplay(Player $p):?DisplayEntity{
        $c=$this->playerControls[$p->getId()]??null;
        if(!is_array($c))return null;
        return $this->displayById($p,(int)($c['displayId']??-1));
    }

    /**
     * Sends a client-only collision cell. This never changes the server world.
     * Altay's UpdateBlockPacket is used directly so the Bedrock client runs its
     * native block collision against the fake cell, while the real world remains untouched.
     */
    private function sendClientCollisionCell(Player $p, int $x, int $y, int $z, int $runtimeId) : void{
        $p->getNetworkSession()->sendDataPacket(UpdateBlockPacket::create(
            BlockPosition::fromVector3(new Vector3($x, $y, $z)),
            $runtimeId,
            UpdateBlockPacket::FLAG_NETWORK,
            UpdateBlockPacket::DATA_LAYER_NORMAL
        ));
    }

    private function realBlockRuntimeId(World $world, int $x, int $y, int $z) : int{
        $block = $world->getBlockAt($x, $y, $z);
        return TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($block->getStateId());
    }

    /** @return array<string,array{0:int,1:int,2:int}> */
    private function desiredClientSolidCells(Player $p) : array{
        $world = $p->getWorld();
        $playerBox = $p->getBoundingBox()->expandedCopy(self::CLIENT_COLLISION_RADIUS, 32.0, self::CLIENT_COLLISION_RADIUS);
        $cells = [];
        foreach($world->getNearbyEntities($playerBox, $p) as $entity){
            if(!$entity instanceof DisplayEntity || $entity->isClosed() || !$entity->isSolid()) continue;
            if($this->hasPlayerControl($p) && $this->getPlayerControlDisplay($p)?->getId() === $entity->getId()) continue;
            [$minX,$minY,$minZ,$maxX,$maxY,$maxZ] = $this->solidCollisionBounds($entity);
            $sx = (int)floor($minX + 0.000001);
            $sy = (int)floor($minY + 0.000001);
            $sz = (int)floor($minZ + 0.000001);
            $ex = (int)ceil($maxX - 0.000001) - 1;
            $ey = (int)ceil($maxY - 0.000001) - 1;
            $ez = (int)ceil($maxZ - 0.000001) - 1;

            // Fake cells are a client-side supplement. For very small or extreme shapes,
            // the server-side AABB resolver remains the precision fallback.
            for($y=$sy; $y<=$ey; ++$y){
                for($x=$sx; $x<=$ex; ++$x){
                    for($z=$sz; $z<=$ez; ++$z){
                        $key=$x.':'.$y.':'.$z;
                        if(isset($cells[$key])) continue;
                        // Never replace an existing colliding world block with a fake block.
                        $worldBlock=$world->getBlockAt($x,$y,$z);
                        if($worldBlock->getCollisionBoxes() !== []) continue;
                        $cells[$key]=[$x,$y,$z];
                        if(count($cells) >= self::MAX_CLIENT_SOLID_CELLS) break 3;
                    }
                }
            }
        }
        return $cells;
    }

    public function invalidateClientSolidCollision(Player $p) : void{
        $pid=$p->getId();
        unset($this->clientSolidCells[$pid],$this->clientSolidWorld[$pid],$this->clientSolidChunk[$pid]);
    }

    public function refreshClientSolidCollision(Player $p) : void{
        if(!$p->isConnected()) return;
        $pid=$p->getId();
        $world=$p->getWorld();
        $worldId=$world->getId();
        $chunkKey=((int)floor($p->getPosition()->x)>>4).':'.((int)floor($p->getPosition()->z)>>4);

        // Bedrock rebuilds the client-side world state after a dimension/world change.
        // The previous collision snapshot therefore cannot be treated as still present.
        // The same applies when the player crosses into a new chunk: chunks may have been
        // unloaded/reloaded on the client, which clears packet-only collision cells.
        $worldChanged=($this->clientSolidWorld[$pid]??null)!==$worldId;
        $chunkChanged=($this->clientSolidChunk[$pid]??null)!==$chunkKey;
        $old=$this->clientSolidCells[$pid]??[];
        if($worldChanged){
            // Never try to restore the previous world's fake cells into the new world.
            // The world/dimension transition replaces the client's visible chunk state.
            $old=[];
            $this->clientSolidWorld[$pid]=$worldId;
            $this->clientSolidChunk[$pid]=$chunkKey;
        }elseif($chunkChanged){
            // Same world, but the client may have unloaded/reloaded nearby chunks.
            // Remove stale cells from the previous snapshot, then resend every desired
            // cell so collision survives client-side chunk rebuilds.
            $this->clientSolidWorld[$pid]=$worldId;
            $this->clientSolidChunk[$pid]=$chunkKey;
        }

        $desired=$this->desiredClientSolidCells($p);
        $barrierRuntime=TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId(
            VanillaBlocks::BARRIER()->getStateId()
        );

        foreach($desired as $key => [$x,$y,$z]){
            if($worldChanged || $chunkChanged || !isset($old[$key])){
                $this->sendClientCollisionCell($p,$x,$y,$z,$barrierRuntime);
            }
        }
        if(!$worldChanged){
            foreach($old as $key => [$x,$y,$z]){
                if(!isset($desired[$key])){
                    $this->sendClientCollisionCell($p,$x,$y,$z,$this->realBlockRuntimeId($world,$x,$y,$z));
                }
            }
        }
        $this->clientSolidCells[$pid]=$desired;
        $this->syncVirtualGroundState($p);
    }

    public function clearClientSolidCollision(Player $p) : void{
        $pid=$p->getId();
        $old=$this->clientSolidCells[$pid]??[];
        unset($this->clientSolidCells[$pid],$this->clientSolidWorld[$pid],$this->clientSolidChunk[$pid]);
        if($old===[]) return;
        // On quit there is no client state that needs restoring. Avoid writing those
        // packet-only cells into whichever world happens to be current.
    }

    public function syncVirtualGroundState(Player $p) : void{
        $bb=$p->getBoundingBox();
        $feet=$bb->minY;
        foreach($p->getWorld()->getNearbyEntities($bb->expandedCopy(3.0,1.25,3.0),$p) as $entity){
            if(!$entity instanceof DisplayEntity || $entity->isClosed() || !$entity->isSolid()) continue;
            if($this->hasPlayerControl($p) && $this->getPlayerControlDisplay($p)?->getId()===$entity->getId()) continue;
            [$minX,$minY,$minZ,$maxX,$maxY,$maxZ]=$this->solidCollisionBounds($entity);
            if($bb->maxX > $minX + 0.001 && $bb->minX < $maxX - 0.001 &&
               $bb->maxZ > $minZ + 0.001 && $bb->minZ < $maxZ - 0.001 &&
               $feet >= $maxY - 0.10 && $feet <= $maxY + 0.16){
                $p->onGround=true;
                $p->isCollidedVertically=true;
                $p->isCollided=true;
                $p->resetFallDistance();
                return;
            }
        }
    }

    /**
     * Resolves BDBlockDisplay's Solid/Walkable state without placing real world blocks.
     * Altay's Player::move() only queries World::getBlockCollisionBoxes(), so entity
     * collision flags alone cannot make a custom display a platform. We therefore
     * constrain the PlayerMoveEvent destination against the display's virtual AABB.
     */
    public function resolveSolidMovement(PlayerMoveEvent $ev):void{
        $p=$ev->getPlayer();
        $from=$ev->getFrom();$to=$ev->getTo();
        if($from->getWorld() !== $to->getWorld())return;
        $width=$p->getSize()->getWidth();$height=$p->getSize()->getHeight();$half=$width/2;
        $wantedX=$to->x-$from->x;$wantedY=$to->y-$from->y;$wantedZ=$to->z-$from->z;
        if(abs($wantedX)<1e-8&&abs($wantedY)<1e-8&&abs($wantedZ)<1e-8)return;
        if(($wantedX*$wantedX+$wantedY*$wantedY+$wantedZ*$wantedZ)>225)return;

        $fromBB=new AxisAlignedBB($from->x-$half,$from->y,$from->z-$half,$from->x+$half,$from->y+$height,$from->z+$half);
        $sweep=$fromBB->addCoord($wantedX,$wantedY,$wantedZ)->expandedCopy(1.75,1.75,1.75);
        $boxes=[];
        foreach($p->getWorld()->getNearbyEntities($sweep,$p) as $entity){
            if(!$entity instanceof DisplayEntity || $entity->isClosed() || !$entity->isSolid())continue;
            foreach($this->solidCollisionBoxes($entity) as $box)$boxes[]=$box;
        }
        if($boxes===[])return;

        // Mirror Altay Entity::move()'s Y -> X -> Z AABB resolution order, but apply it
        // to BDBlockDisplay virtual collision boxes after PlayerMoveEvent.
        $moveBB=clone $fromBB;
        $dy=$wantedY;
        foreach($boxes as $box)$dy=$box->calculateYOffset($moveBB,$dy);
        $moveBB->offset(0,$dy,0);
        $dx=$wantedX;
        foreach($boxes as $box)$dx=$box->calculateXOffset($moveBB,$dx);
        $moveBB->offset($dx,0,0);
        $dz=$wantedZ;
        foreach($boxes as $box)$dz=$box->calculateZOffset($moveBB,$dz);
        $moveBB->offset(0,0,$dz);

        $x=($moveBB->minX+$moveBB->maxX)/2;$y=$moveBB->minY;$z=($moveBB->minZ+$moveBB->maxZ)/2;
        $horizontalCollision=($dx!==$wantedX)||($dz!==$wantedZ);
        $verticalCollision=($dy!==$wantedY);
        $virtualGround=false;
        foreach($boxes as $box){
            $horizontal=($moveBB->maxX>$box->minX+0.001&&$moveBB->minX<$box->maxX-0.001&&$moveBB->maxZ>$box->minZ+0.001&&$moveBB->minZ<$box->maxZ-0.001);
            if($horizontal && abs($moveBB->minY-$box->maxY)<=0.03125){$virtualGround=true;break;}
        }
        if($virtualGround){
            $p->onGround=true;
            $p->isCollidedVertically=true;
            $p->isCollided=$p->isCollided||$verticalCollision;
            $p->resetFallDistance();
        }else{
            // Preserve Altay's normal state for leaving the virtual surface.
            $p->onGround=$p->isOnGround() && !$verticalCollision ? true : $p->isOnGround();
            if($verticalCollision)$p->isCollidedVertically=true;
            if($horizontalCollision)$p->isCollidedHorizontally=true;
            $p->isCollided=$p->isCollided||$verticalCollision||$horizontalCollision;
        }

        $changed=abs($x-$to->x)>0.000001||abs($y-$to->y)>0.000001||abs($z-$to->z)>0.000001;
        if($changed){
            $ev->setTo(new Location($x,$y,$z,$to->getWorld(),$to->yaw,$to->pitch));
        }
    }

    /** @return AxisAlignedBB[] */
    private function solidCollisionBoxes(DisplayEntity $d):array{
        $l=$d->getLocation();$s=$d->getDisplaySize();$row=BlockRegistry::row($d->getBlockId());$shape=$row[0]??1;
        $minX=$l->x-$s/2;$maxX=$l->x+$s/2;$minZ=$l->z-$s/2;$maxZ=$l->z+$s/2;$minY=$l->y;$maxY=$l->y+$s;
        if($shape===2){
            if($d->getVariant()===1)$minY+=$s/2;else $maxY-=$s/2;
        }
        return [new AxisAlignedBB($minX,$minY,$minZ,$maxX,$maxY,$maxZ)];
    }

    /** @return array{0:float,1:float,2:float,3:float,4:float,5:float} */
    private function solidCollisionBounds(DisplayEntity $d):array{
        $l=$d->getLocation();
        $s=$d->getDisplaySize();
        $minX=$l->x-$s/2.0;
        $maxX=$l->x+$s/2.0;
        $minZ=$l->z-$s/2.0;
        $maxZ=$l->z+$s/2.0;
        $row=BlockRegistry::row($d->getBlockId());
        $shape=$row[0]??1;
        $minY=$l->y;
        $maxY=$l->y+$s;
        if($shape===2){
            // Variant 0 = bottom slab, variant 1 = top slab.
            if($d->getVariant()===1){
                $minY+=$s/2.0;
            }else{
                $maxY-=$s/2.0;
            }
        }
        return [$minX,$minY,$minZ,$maxX,$maxY,$maxZ];
    }

    public function tickPlayerControl(\pocketmine\event\player\PlayerMoveEvent $ev):void{
        $p=$ev->getPlayer();
        $id=$p->getId();$control=$this->playerControls[$id]??null;
        if(!is_array($control))return;
        $d=$this->displayById($p,(int)($control['displayId']??-1));
        if($d===null||$d->getWorld()!==$p->getWorld()){ $this->stopPlayerControl($p,false); return; }
        $from=$ev->getFrom();$to=$ev->getTo();
        $delta=new Vector3($to->x-$from->x,$to->y-$from->y,$to->z-$from->z);
        $dist2=$delta->lengthSquared();
        if($dist2<0.00000001)return;
        // Walking, strafing, jump and sneak movement is transferred 1:1.
        // Ignore teleport-sized jumps so a server teleport cannot drag a display across the world.
        if($dist2>25.0){return;}
        $l=$d->getLocation();
        $np=new Vector3($l->x+$delta->x,$l->y+$delta->y,$l->z+$delta->z);
        $d->teleport($np,$l->yaw,$l->pitch);
        $this->playerControls[$id]['last']=$to->asVector3();
        // Player-control runs on PlayerMoveEvent, so move existing companion
        // model faces directly instead of rebuilding/spawning them every move.
        foreach($d->getWorld()->getEntities() as $e){
            if($e instanceof ModelFaceEntity && !$e->isClosed() && $e->ownerDisplayId===$d->getId()){
                $ml=$e->getLocation();
                $e->teleport($np,$ml->yaw,$ml->pitch);
            }
        }
    }

    public function startDrag(Player $p,HandleEntity $h):void{
        $d=$this->displayById($p,$h->ownerDisplayId);if($d===null)return;
        $current=$this->drags[$p->getId()]??null;if(is_array($current)&&($current['handleId']??-1)===$h->getId()){$this->stopDrag($p);$p->sendMessage('§7Drag stopped.');return;}
        $this->stopDrag($p);
        $rayOrigin=$p->getEyePos();$rayDir=$p->getDirectionVector();$center=$this->editorCenter($d);
        if($h->kind===3){
            $axis=$this->axisVector($h->axis);$v=$this->rayPlaneVector($rayOrigin,$rayDir,$center,$axis);if($v===null)$v=$this->fallbackPlaneVector($h->axis);
            $this->drags[$p->getId()]=['displayId'=>$d->getId(),'handleId'=>$h->getId(),'type'=>'rotate','axis'=>$h->axis,'lastVector'=>$v,'lastTick'=>$this->plugin->getServer()->getTick()];
        }elseif($h->kind===4){
            $axis=new Vector3(1/sqrt(3),1/sqrt(3),1/sqrt(3));$t=$this->rayLineParameter($rayOrigin,$rayDir,$center,$axis);
            $this->drags[$p->getId()]=['displayId'=>$d->getId(),'handleId'=>$h->getId(),'type'=>'scale','axis'=>3,'startT'=>$t,'startSize'=>$d->getDisplaySize(),'center'=>$center,'lastTick'=>$this->plugin->getServer()->getTick()];
        }else{
            $axis=$this->axisVector($h->axis);$t=$this->rayLineParameter($rayOrigin,$rayDir,$center,$axis);
            $this->drags[$p->getId()]=['displayId'=>$d->getId(),'handleId'=>$h->getId(),'type'=>'move','axis'=>$h->axis,'startT'=>$t,'startPos'=>$d->getPosition()->asVector3(),'center'=>$center,'lastTick'=>$this->plugin->getServer()->getTick()];
        }
        $h->setHot(true);$p->sendMessage('§aDrag active. Move your view to edit; tap the same handle again to stop.');
    }
    public function stopDrag(Player $p):void{
        $drag=$this->drags[$p->getId()]??null;if(is_array($drag)){foreach($p->getWorld()->getEntities() as $e)if($e instanceof HandleEntity&&$e->getId()===(int)($drag['handleId']??-1)){$e->setHot(false);break;}}unset($this->drags[$p->getId()]);
    }
    public function hasActiveDrag(Player $p):bool{return isset($this->drags[$p->getId()]);}
    public function tickDrag(Player $p):void{
        $drag=$this->drags[$p->getId()]??null;if(!is_array($drag))return;$d=$this->displayById($p,(int)($drag['displayId']??-1));$h=$this->entityById($p,(int)($drag['handleId']??-1));
        if($d===null||$h===null){$this->stopDrag($p);return;}
                $origin=$p->getEyePos();$dir=$p->getDirectionVector();$center=$this->editorCenter($d);
        if($drag['type']==='move'){
            $center=$drag['center'];$axis=$this->axisVector((int)$drag['axis']);$t=$this->rayLineParameter($origin,$dir,$center,$axis);$delta=DisplayMath::clamp($t-(float)$drag['startT'],-4.0,4.0);$base=$drag['startPos'];$np=$base->addVector($axis->multiply($delta));
            if($np->distanceSquared($d->getPosition())>0.0000005)$d->teleport(new Location($np->x,$np->y,$np->z,$d->getWorld(),$d->getLocation()->yaw,$d->getLocation()->pitch));
        }elseif($drag['type']==='rotate'){
            $axis=$this->axisVector((int)$drag['axis']);$v=$this->rayPlaneVector($origin,$dir,$center,$axis);if($v===null)$v=$drag['lastVector'];if(DisplayMath::length($v)<1e-6)return;$v=DisplayMath::unit($v);$prev=$drag['lastVector'];$cross=DisplayMath::cross($prev,$v);$angle=atan2(DisplayMath::dot($axis,$cross),DisplayMath::dot($prev,$v));$angle=DisplayMath::clamp(rad2deg($angle),-20.0,20.0);
            if(abs($angle)>0.001){$this->rotateAxis($d,(int)$drag['axis'],$angle);$this->drags[$p->getId()]['lastVector']=$v;}
        }else{
            $center=$drag['center'];$axis=new Vector3(1/sqrt(3),1/sqrt(3),1/sqrt(3));$t=$this->rayLineParameter($origin,$dir,$center,$axis);$delta=DisplayMath::clamp($t-(float)$drag['startT'],-2.0,2.0);$size=DisplayMath::clamp((float)$drag['startSize']+$delta/(.75*sqrt(3)),.0625,16.0);$d->setDisplaySize($size);
        }
        $this->syncModel($d,0);$this->updateHandlePositions($p);
    }
    private function axisVector(int $axis):Vector3{return $axis===0?new Vector3(1,0,0):($axis===1?new Vector3(0,1,0):new Vector3(0,0,1));}
    private function fallbackPlaneVector(int $axis):Vector3{return $axis===0?new Vector3(0,0,1):new Vector3(1,0,0);}
    private function rayPlaneVector(Vector3 $origin,Vector3 $dir,Vector3 $planePoint,Vector3 $normal):?Vector3{
        $den=DisplayMath::dot($dir,$normal);if(abs($den)<1e-6)return null;$t=DisplayMath::dot(DisplayMath::sub($planePoint,$origin),$normal)/$den;if($t<0)return null;$hit=$origin->addVector($dir->multiply($t));$v=DisplayMath::sub($hit,$planePoint);return DisplayMath::length($v)<1e-6?null:DisplayMath::unit($v);
    }
    private function rayLineParameter(Vector3 $origin,Vector3 $dir,Vector3 $linePoint,Vector3 $axis):float{
        $w0=DisplayMath::sub($origin,$linePoint);$a=DisplayMath::dot($dir,$dir);$b=DisplayMath::dot($dir,$axis);$c=DisplayMath::dot($axis,$axis);$d=DisplayMath::dot($dir,$w0);$e=DisplayMath::dot($axis,$w0);$den=$a*$c-$b*$b;
        if(abs($den)<1e-7)return -$e/$c;return ($a*$e-$b*$d)/$den;
    }
    private function displayById(Player $p,int $id):?DisplayEntity{foreach($p->getWorld()->getEntities() as $e)if($e instanceof DisplayEntity&&$e->getId()===$id&&!$e->isClosed())return $e;return null;}
    private function entityById(Player $p,int $id):?HandleEntity{foreach($p->getWorld()->getEntities() as $e)if($e instanceof HandleEntity&&$e->getId()===$id&&!$e->isClosed())return $e;return null;}

    public function applyHandle(Player $p,HandleEntity $h):void{
        if($h->kind===3||$h->kind===4||$h->kind===0||$h->kind===1||$h->kind===2){$this->startDrag($p,$h);return;}
    }
    public function nudge(DisplayEntity $d,int $axis,float $amount):void{$l=$d->getLocation();$x=$l->x;$y=$l->y;$z=$l->z;if($axis===0)$x+=$amount;elseif($axis===1)$y+=$amount;else $z+=$amount;$d->teleport(new Location($x,$y,$z,$d->getWorld(),$l->yaw,$l->pitch));}
    private function rotateAxis(DisplayEntity $d,int $axis,float $delta):void{$loc=$d->getLocation();$rx=$loc->pitch;$ry=$loc->yaw;$rz=$d->getRoll();if($axis===0)$rx+=$delta;elseif($axis===1)$ry+=$delta;else $rz+=$delta;$d->setRotation($ry,$rx);$d->setRoll($rz);}
    public function rotateBy(DisplayEntity $d,int $axis,float $delta):void{$this->rotateAxis($d,$axis,$delta);}
    public function syncModel(DisplayEntity $d,int $moveCode=0):void{
        $row=BlockRegistry::row($d->getBlockId());if($row===null)return;$shape=$row[0];$faceTex=array_slice($row,1,6);$scale=$d->getDisplaySize();$names=['up','down','north','south','east','west'];
        $r=$d->getLocation();$v=max(0,min(BlockRegistry::variantCount($shape)-1,$d->getVariant()));if($d->getVariant()!==$v)$d->setVariant($v);
        $existing=[];foreach($d->getWorld()->getEntities() as $e)if($e instanceof ModelFaceEntity&&!$e->isClosed()&&$e->ownerDisplayId===$d->getId())$existing[$e::getNetworkTypeId()]=$e;$wanted=[];
        foreach($names as $i=>$face){$class=ModelEntityRegistry::classFor($shape,$v,$face);if($class===null)continue;$net=$class::getNetworkTypeId();$m=$existing[$net]??null;if($m===null){$m=new $class(new Location($r->x,$r->y,$r->z,$r->getWorld(),$r->yaw,$r->pitch),null);}else{$m->teleport(new Location($r->x,$r->y,$r->z,$r->getWorld(),$r->yaw,$r->pitch));}$m->configure($faceTex[$i],$shape,$v,$face,$scale,$r->yaw,$r->pitch,$d->getRoll(),$d->getId(),$moveCode);$m->spawnToAll();$wanted[$m->getId()]=true;}
        foreach($existing as $m)if(!isset($wanted[$m->getId()]))$m->flagForDespawn();$d->syncModelMetadata();
    }
    public function deleteDisplay(DisplayEntity $d):void{foreach($d->getWorld()->getEntities() as $e)if($e instanceof ModelFaceEntity&&$e->ownerDisplayId===$d->getId())$e->flagForDespawn();$d->flagForDespawn();}
    public function duplicate(Player $p,DisplayEntity $d):DisplayEntity{$pos=$d->getPosition()->add($d->getDisplaySize(),0,0);$n=new DisplayEntity(new Location($pos->x,$pos->y,$pos->z,$p->getWorld(),$d->getLocation()->yaw,$d->getLocation()->pitch),null);$n->setBlockId($d->getBlockId());$n->setDisplaySize($d->getDisplaySize());$n->setRoll($d->getRoll());$n->setSolid($d->isSolid());$n->spawnToAll();$this->syncModel($n);return $n;}
}

final class ModelEntityRegistry{
    /** @var array<string,string> */ private static array $map=[];
    public static function add(int $s,int $v,string $face,string $class):void{self::$map["$s:$v:$face"]=$class;}
    public static function classFor(int $s,int $v,string $face):?string{return self::$map["$s:$v:$face"]??null;}
}

final class main extends PluginBase implements Listener{
    private BlockDisplayManager $manager;
    private const IDS=['wand'=>30001,'rotate'=>30002,'scale'=>30003,'group'=>30004];
    private function itemKind(Item $i):?string{
        if($i instanceof WandItem)return 'wand';
        if($i instanceof RotateWandItem)return 'rotate';
        if($i instanceof ScaleWandItem)return 'scale';
        if($i instanceof GroupToolItem)return 'group';
        return match($i->getTypeId()){30001=>'wand',30002=>'rotate',30003=>'scale',30004=>'group',default=>null};
    }
    public function itemKindPublic(Item $i):?string{return $this->itemKind($i);}
    protected function onEnable():void{
        if(!$this->getServer()->getPluginManager()->getPlugin('Customies')){$this->getLogger()->error('Customies 1.4.0 is required.');$this->getServer()->getPluginManager()->disablePlugin($this);return;}
        $this->manager=new BlockDisplayManager($this);
        ModelRegistryBootstrap::register();
        $this->registerEntities();$this->registerItems();$this->installResourcePack();
        $this->getServer()->getPluginManager()->registerEvents($this,$this);$this->getServer()->getCommandMap()->register('bdbd',new BDBlockCommand($this));
        $this->getScheduler()->scheduleRepeatingTask(new \pocketmine\scheduler\ClosureTask(function(){foreach($this->getServer()->getOnlinePlayers() as $player){$this->manager->refreshClientSolidCollision($player);} }),1);
        $this->getScheduler()->scheduleRepeatingTask(new \pocketmine\scheduler\ClosureTask(function(){foreach($this->getServer()->getWorldManager()->getWorlds() as $w){foreach($w->getEntities() as $e){if($e instanceof DisplayEntity && !$e->isClosed()){$this->manager->syncModel($e);}}}}),40);
        $this->getLogger()->info('BDBlockDisplay '.BD_VERSION.' enabled. Altay/Customies renderer bridge active (variant/mark_variant/skin_id + virtual walkable collision).');
    }
    private function registerEntities():void{
        CustomiesEntityFactory::getInstance()->registerEntity(DisplayEntity::class,'bd:display');CustomiesEntityFactory::getInstance()->registerEntity(HandleEntity::class,'bd:handle');
        foreach(ModelRegistryBootstrap::all() as $pair){CustomiesEntityFactory::getInstance()->registerEntity($pair[0],$pair[1]);}
    }
    private function registerItems():void{
        CustomiesItemFactory::getInstance()->registerItem(fn()=>new WandItem(new ItemIdentifier(self::IDS['wand'])),'bd:wand');
        CustomiesItemFactory::getInstance()->registerItem(fn()=>new RotateWandItem(new ItemIdentifier(self::IDS['rotate'])),'bd:wand_rotate');
        CustomiesItemFactory::getInstance()->registerItem(fn()=>new ScaleWandItem(new ItemIdentifier(self::IDS['scale'])),'bd:wand_scale');
        CustomiesItemFactory::getInstance()->registerItem(fn()=>new GroupToolItem(new ItemIdentifier(self::IDS['group'])),'bd:group_tool');
    }
    private function installResourcePack():void{
        $src=$this->getResource('BDBlockDisplay_RP.mcpack');
        if($src===null)return;
        $path=$this->getDataFolder().'BDBlockDisplay_RP.mcpack';
        @mkdir($this->getDataFolder(),0777,true);
        file_put_contents($path,stream_get_contents($src));
        fclose($src);
        $pack=new ZippedResourcePack($path);
        $rm=$this->getServer()->getResourcePackManager();
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
    public function giveTools(Player $p):void{$inv=$p->getInventory();foreach(['bd:wand','bd:wand_rotate','bd:wand_scale','bd:group_tool'] as $id){$item=CustomiesItemFactory::getInstance()->get($id);foreach($inv->addItem($item) as $drop){$p->getWorld()->dropItem($p->getPosition(),$drop);}}$p->sendMessage('§aBDBlockDisplay tools given.');}
    public function onItemUse(PlayerItemUseEvent $ev):void{
        $k=$this->itemKind($ev->getItem());if($k===null)return;
        $ev->cancel();$p=$ev->getPlayer();
        if($this->manager->hasPlayerControl($p)){
            $d=$this->manager->getPlayerControlDisplay($p);
            $this->manager->stopPlayerControl($p,true);
            if($d!==null)$this->openMenu($p,$d);
            return;
        }
        $target=$this->manager->findInSight($p,[DisplayEntity::class,HandleEntity::class]);
        if($target instanceof HandleEntity){$this->manager->applyHandle($p,$target);return;}
        if($target instanceof DisplayEntity){
            $this->manager->select($p,$target);
            if($k==='group'){$this->manager->toggleGroupMember($p,$target);return;}
            if($k==='rotate'){$this->manager->setMode($target,2);$this->manager->refreshHandles($p);$p->sendMessage('§eRotate tool active.');return;}
            if($k==='scale'){$this->manager->setMode($target,3);$this->manager->refreshHandles($p);$p->sendMessage('§dScale tool active.');return;}
            $this->openMenu($p,$target);return;
        }
        if($k==='group'){$this->openGroupMenu($p);return;}
        if($k==='wand'){$this->spawnFromHeld($p);return;}
        $p->sendMessage('§7No display in sight.');
    }
    public function onEntityInteract(PlayerEntityInteractEvent $ev):void{
        $e=$ev->getEntity();if(!$e instanceof DisplayEntity && !$e instanceof HandleEntity)return;
        $ev->cancel();$p=$ev->getPlayer();$kind=$this->itemKind($p->getInventory()->getItemInHand());
        if($e instanceof HandleEntity){$this->manager->startDrag($p,$e);return;}
        if($kind==='group'){$this->manager->toggleGroupMember($p,$e);return;}
        if($kind==='rotate'){$this->manager->stopDrag($p);$this->manager->select($p,$e);$this->manager->setMode($e,2);$this->manager->refreshHandles($p);$p->sendMessage('§eRotate tool active.');return;}
        if($kind==='scale'){$this->manager->stopDrag($p);$this->manager->select($p,$e);$this->manager->setMode($e,3);$this->manager->refreshHandles($p);$p->sendMessage('§dScale tool active.');return;}
        if($this->manager->hasPlayerControl($p)&&$this->manager->getPlayerControlDisplay($p)?->getId()===$e->getId()){$this->manager->stopPlayerControl($p,true);$this->openMenu($p,$e);return;}
        $this->manager->stopDrag($p);$this->manager->select($p,$e);$this->openMenu($p,$e);
    }
    public function onDamage(EntityDamageByEntityEvent $ev):void{
        $e=$ev->getEntity();if(!$e instanceof DisplayEntity && !$e instanceof HandleEntity)return;
        $ev->cancel();$p=$ev->getDamager();if(!$p instanceof Player)return;
        $kind=$this->itemKind($p->getInventory()->getItemInHand());
        if($e instanceof HandleEntity){$this->manager->startDrag($p,$e);return;}
        if($kind==='group'){$this->manager->toggleGroupMember($p,$e);return;}
        if($kind==='rotate'){$this->manager->stopDrag($p);$this->manager->select($p,$e);$this->manager->setMode($e,2);$this->manager->refreshHandles($p);$p->sendMessage('§eRotate tool active.');return;}
        if($kind==='scale'){$this->manager->stopDrag($p);$this->manager->select($p,$e);$this->manager->setMode($e,3);$this->manager->refreshHandles($p);$p->sendMessage('§dScale tool active.');return;}
        if($this->manager->hasPlayerControl($p)&&$this->manager->getPlayerControlDisplay($p)?->getId()===$e->getId()){$this->manager->stopPlayerControl($p,true);$this->openMenu($p,$e);return;}
        $this->manager->stopDrag($p);$this->manager->select($p,$e);$this->openMenu($p,$e);
    }
    public function onMove(PlayerMoveEvent $ev):void{if($ev->isCancelled())return;$p=$ev->getPlayer();$from=$ev->getFrom();$to=$ev->getTo();if($from->getWorld()->getId()!==$to->getWorld()->getId()){$this->manager->invalidateClientSolidCollision($p);}$this->manager->refreshClientSolidCollision($p);if(!$this->manager->hasPlayerControl($p))$this->manager->resolveSolidMovement($ev);if($this->manager->hasPlayerControl($p))$this->manager->tickPlayerControl($ev);elseif($this->manager->hasActiveDrag($p))$this->manager->tickDrag($p);}
    public function onQuit(PlayerQuitEvent $ev):void{$this->manager->clearClientSolidCollision($ev->getPlayer());$this->manager->stopPlayerControl($ev->getPlayer(),false);$this->manager->clearSelection($ev->getPlayer());}
    private function spawnFromHeld(Player $p):void{$item=$p->getInventory()->getItemInHand();$name=method_exists($item,'getVanillaName')?$item->getVanillaName():$item->getName();$id=strtolower(str_replace([' ','-'],'_',$name));$id=$this->normalizeFromItem($id);if($id===null){$p->sendMessage('§cThis block is not in the BlockDisplay 0.5.1 model database. Use /bdbd spawn <block>.');return;}$pos=$p->getPosition()->addVector($p->getDirectionVector()->multiply(3));$d=new DisplayEntity(new Location($pos->x,$pos->y,$pos->z,$p->getWorld(),$p->getLocation()->yaw,$p->getLocation()->pitch),null);$d->setBlockId($id);$d->setDisplaySize(1);$d->setMode(1);$d->spawnToAll();$this->manager->syncModel($d);$this->manager->select($p,$d);}
    private function normalizeFromItem(string $id):?string{return BlockRegistry::canonical($id) ?? BlockRegistry::canonical(str_replace('minecraft:','',$id));}
    private function iconButton(string $text,?string $path=null):array{
        $b=['text'=>$text];
        if($path!==null)$b['image']=['type'=>'path','data'=>$path];
        return $b;
    }

    private function openMenu(Player $p,DisplayEntity $d):void{
        $control=$this->manager->hasPlayerControl($p)&&$this->manager->getPlayerControlDisplay($p)?->getId()===$d->getId();
        $mode=BlockDisplayManager::MODE_NAMES[$d->getMode()]??'Move';
        $status=$control?'§aPLAYER CONTROL ACTIVE':'§7Player Control: §fOFF';
        $buttons=[
            $this->iconButton('§b🧍 PLAYER CONTROL\n§7Walk to move the block','textures/ui/bd/player_control'),
            $this->iconButton('§e⟳ ROTATION / LEAN\n§75° • 10° • 15° • 30° • 45°','textures/ui/bd/rotate'),
            $this->iconButton('§d↗ SCALE\n§75% • 10% • 25% • 50%','textures/ui/bd/scale'),
            $this->iconButton('§a🎯 EXACT VALUES\n§7Position • Rotation • Size','textures/ui/bd/exact'),
            $this->iconButton('§f⛏ SET BLOCK','textures/ui/bd/setblock'),
            $this->iconButton('§6↔ QUICK MOVE\n§7X • Y • Z','textures/ui/bd/right'),
            $this->iconButton('§7◇ VARIANT +','textures/ui/bd/variant'),
            $this->iconButton('§e⧉ DUPLICATE','textures/ui/bd/duplicate'),
            $this->iconButton('§b⊞ GROUP','textures/ui/bd/player_control'),
            $this->iconButton('§b♿ WALKABLE','textures/ui/bd/walkable'),
            $this->iconButton('§6↻ REFRESH HANDLES','textures/ui/bd/refresh'),
            $this->iconButton($control?'§c⏹ STOP PLAYER CONTROL':'§7⏹ STOP DRAG','textures/ui/bd/stop'),
            $this->iconButton('§c✕ DELETE','textures/ui/bd/delete'),
            $this->iconButton('§f✕ CLOSE','textures/ui/bd/close'),
        ];
        $json=json_encode(['type'=>'form','title'=>'§lBDBLOCKDISPLAY','content'=>'§7Block: §f'.$d->getBlockId()."\n".'§7Size: §f'.round($d->getDisplaySize(),4)."\n".'§7Rotation: §f'.round($d->getLocation()->pitch,3).', '.round($d->getLocation()->yaw,3).', '.round($d->getRoll(),3)."\n".'§7Mode: §f'.$mode."\n".$status."\n§8Player Control is continuous: movement follows your player 1:1.",'buttons'=>$buttons], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $form=new SimpleForm($json,function(Player $p,$data)use($d){
            if(!is_int($data))return;
            switch($data){
                case 0:
                    if($this->manager->hasPlayerControl($p)){ $this->manager->stopPlayerControl($p,true); $this->openMenu($p,$d); }
                    else { $this->manager->startPlayerControl($p,$d); }
                    return;
                case 1:$this->openRotationLeanForm($p,$d);return;
                case 2:$this->openScaleForm($p,$d);return;
                case 3:$this->exactForm($p,$d);return;
                case 4:$this->spawnBlockForm($p,$d);return;
                case 5:$this->openQuickMoveForm($p,$d);return;
                case 6:$d->setVariant($d->getVariant()+1);$p->sendMessage('§aShape / Variant: '.$d->getVariant());break;
                case 7:$this->manager->duplicate($p,$d);break;
                case 8:$this->openGroupMenu($p);return;
                case 9:$d->setSolid(!$d->isSolid());$p->sendMessage('§eWalkable / Solid: '.($d->isSolid()?'ON':'OFF').' §7(virtual collision)');break;
                case 10:$this->manager->refreshHandles($p);break;
                case 11:$this->manager->stopPlayerControl($p,true);$this->manager->stopDrag($p);break;
                case 12:$this->manager->deleteDisplay($d);$this->manager->clearSelection($p);return;
                case 13:return;
                default:return;
            }
            $this->manager->syncModel($d);$this->manager->updateHandlePositions($p);$this->openMenu($p,$d);
        });
        $p->sendForm($form);
    }

    private function openQuickMoveForm(Player $p,DisplayEntity $d):void{
        $f=new SimpleForm(json_encode(['type'=>'form','title'=>'§lQUICK MOVE','content'=>'§7One tap = '.BlockDisplayManager::MOVE_STEP.' block.\n§8For continuous movement use PLAYER CONTROL.','buttons'=>[
            $this->iconButton('§a→ X +','textures/ui/bd/right'),$this->iconButton('§c← X -','textures/ui/bd/left'),
            $this->iconButton('§a↑ Y +','textures/ui/bd/up'),$this->iconButton('§c↓ Y -','textures/ui/bd/down'),
            $this->iconButton('§a→ Z +','textures/ui/bd/right'),$this->iconButton('§c← Z -','textures/ui/bd/left'),
            $this->iconButton('§7← BACK','textures/ui/bd/left')
        ]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),function(Player $p,$data)use($d){
            if(!is_int($data))return;
            if($data===0)$this->manager->nudge($d,0,BlockDisplayManager::MOVE_STEP);
            elseif($data===1)$this->manager->nudge($d,0,-BlockDisplayManager::MOVE_STEP);
            elseif($data===2)$this->manager->nudge($d,1,BlockDisplayManager::MOVE_STEP);
            elseif($data===3)$this->manager->nudge($d,1,-BlockDisplayManager::MOVE_STEP);
            elseif($data===4)$this->manager->nudge($d,2,BlockDisplayManager::MOVE_STEP);
            elseif($data===5)$this->manager->nudge($d,2,-BlockDisplayManager::MOVE_STEP);
            else { $this->openMenu($p,$d); return; }
            $this->manager->syncModel($d);$this->manager->updateHandlePositions($p);$this->openQuickMoveForm($p,$d);
        });
        $p->sendForm($f);
    }

    private function openRotationLeanForm(Player $p,DisplayEntity $d):void{
        $buttons=[
            $this->iconButton('§cX −5°','textures/ui/bd/minus'),$this->iconButton('§aX +5°','textures/ui/bd/plus'),
            $this->iconButton('§cY −5°','textures/ui/bd/minus'),$this->iconButton('§aY +5°','textures/ui/bd/plus'),
            $this->iconButton('§cSIDE LEAN −5°','textures/ui/bd/minus'),$this->iconButton('§aSIDE LEAN +5°','textures/ui/bd/plus'),
            $this->iconButton('§cLEAN −10°','textures/ui/bd/minus'),$this->iconButton('§aLEAN +10°','textures/ui/bd/plus'),
            $this->iconButton('§cLEAN −15°','textures/ui/bd/minus'),$this->iconButton('§aLEAN +15°','textures/ui/bd/plus'),
            $this->iconButton('§cLEAN −30°','textures/ui/bd/minus'),$this->iconButton('§aLEAN +30°','textures/ui/bd/plus'),
            $this->iconButton('§cLEAN −45°','textures/ui/bd/minus'),$this->iconButton('§aLEAN +45°','textures/ui/bd/plus'),
            $this->iconButton('§7↺ RESET ROTATION','textures/ui/bd/refresh'),
            $this->iconButton('§7← BACK','textures/ui/bd/left')
        ];
        $f=new SimpleForm(json_encode(['type'=>'form','title'=>'§lROTATION / LEAN','content'=>'§7X/Y rotate the block. Z is the side-lean axis.\n§fCurrent Z lean: '.round($d->getRoll(),2).'°','buttons'=>$buttons],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),function(Player $p,$data)use($d){
            if(!is_int($data))return;
            $map=[0=>[0,-5],1=>[0,5],2=>[1,-5],3=>[1,5],4=>[2,-5],5=>[2,5],6=>[2,-10],7=>[2,10],8=>[2,-15],9=>[2,15],10=>[2,-30],11=>[2,30],12=>[2,-45],13=>[2,45]];
            if(isset($map[$data])){[$axis,$delta]=$map[$data];$this->manager->rotateBy($d,$axis,$delta);$this->manager->syncModel($d);$this->openRotationLeanForm($p,$d);return;}
            if($data===14){$l=$d->getLocation();$d->teleport($d->getPosition(),0,0);$d->setRoll(0);$this->manager->syncModel($d);$this->openRotationLeanForm($p,$d);return;}
            $this->openMenu($p,$d);
        });
        $p->sendForm($f);
    }

    private function openScaleForm(Player $p,DisplayEntity $d):void{
        $buttons=[];foreach([[-50,'−50%'],[-25,'−25%'],[-10,'−10%'],[5,'+5%'],[10,'+10%'],[25,'+25%'],[50,'+50%']] as [$pct,$label])$buttons[]=$this->iconButton($label,$pct<0?'textures/ui/bd/minus':'textures/ui/bd/plus');
        $buttons[]=$this->iconButton('§7BACK','textures/ui/bd/left');
        $f=new SimpleForm(json_encode(['type'=>'form','title'=>'§lSCALE','content'=>'§7Current size: §f'.round($d->getDisplaySize(),4).'§r\n§8Percent changes are relative to current size.','buttons'=>$buttons]),function(Player $p,$data)use($d){if(!is_int($data))return;$vals=[-50,-25,-10,5,10,25,50];if(isset($vals[$data])){$factor=1+$vals[$data]/100;$d->setDisplaySize($d->getDisplaySize()*$factor);$this->manager->syncModel($d);$this->manager->refreshHandles($p);$this->openScaleForm($p,$d);}});$p->sendForm($f);
    }

    private function openGroupMenu(Player $p):void{
        $count=$this->manager->groupCount($p);
        $buttons=[
            $this->iconButton('§b⊞ SELECT ALL NEARBY\n§7Radius: 16 blocks','textures/ui/bd/player_control'),
            $this->iconButton('§7CLEAR GROUP','textures/ui/bd/delete'),
            $this->iconButton('§a→ MOVE X+ 0.5','textures/ui/bd/right'),
            $this->iconButton('§c← MOVE X− 0.5','textures/ui/bd/left'),
            $this->iconButton('§a↑ MOVE Y+ 0.5','textures/ui/bd/up'),
            $this->iconButton('§c↓ MOVE Y− 0.5','textures/ui/bd/down'),
            $this->iconButton('§a↗ MOVE Z+ 0.5','textures/ui/bd/right'),
            $this->iconButton('§c↙ MOVE Z− 0.5','textures/ui/bd/left'),
            $this->iconButton('§e⟳ ROTATE Y +15°','textures/ui/bd/plus'),
            $this->iconButton('§e⟲ ROTATE Y −15°','textures/ui/bd/minus'),
            $this->iconButton('§d SCALE +10%','textures/ui/bd/plus'),
            $this->iconButton('§c SCALE −10%','textures/ui/bd/minus'),
            $this->iconButton('§b⧉ DUPLICATE GROUP','textures/ui/bd/duplicate'),
            $this->iconButton('§c✕ DELETE GROUP','textures/ui/bd/delete'),
            $this->iconButton('§f← BACK','textures/ui/bd/left'),
        ];
        $f=new SimpleForm(json_encode(['type'=>'form','title'=>'§lGROUP CONTROL','content'=>'§7Selected members: §f'.$count.'\n§8Use the Group Tool to tap displays and add/remove them.','buttons'=>$buttons],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),function(Player $p,$data){
            if(!is_int($data))return;
            switch($data){
                case 0:$this->manager->selectAllNearbyGroup($p,16);break;
                case 1:$this->manager->clearGroup($p);break;
                case 2:$this->manager->moveGroup($p,.5,0,0);break;
                case 3:$this->manager->moveGroup($p,-.5,0,0);break;
                case 4:$this->manager->moveGroup($p,0,.5,0);break;
                case 5:$this->manager->moveGroup($p,0,-.5,0);break;
                case 6:$this->manager->moveGroup($p,0,0,.5);break;
                case 7:$this->manager->moveGroup($p,0,0,-.5);break;
                case 8:$this->manager->rotateGroup($p,1,15);break;
                case 9:$this->manager->rotateGroup($p,1,-15);break;
                case 10:$this->manager->scaleGroup($p,1.10);break;
                case 11:$this->manager->scaleGroup($p,.90);break;
                case 12:$this->manager->duplicateGroup($p);break;
                case 13:$this->manager->deleteGroup($p);return;
                default:$back=$this->manager->selected($p) ?? ($this->manager->groupDisplays($p)[0] ?? null);if($back instanceof DisplayEntity)$this->openMenu($p,$back);return;
            }
            $ds=$this->manager->groupDisplays($p);foreach($ds as $d){$this->manager->syncModel($d);} $this->openGroupMenu($p);
        });
        $p->sendForm($f);
    }

    private function spawnBlockForm(Player $p,DisplayEntity $d):void{$opts=array_keys(BlockRegistry::BLOCKS);sort($opts);$content=[['type'=>'dropdown','text'=>'Block ID','options'=>$opts,'default'=>array_search($d->getBlockId(),$opts,true)?:0],['type'=>'input','text'=>'Or type block ID','default'=>$d->getBlockId()]];$f=new SimpleForm(json_encode(['type'=>'custom_form','title'=>'Set Block','content'=>$content]),function(Player $p,$data)use($d,$opts){if(!is_array($data))return;$id=trim((string)($data[1]??''));if($id===''&&isset($data[0]))$id=$opts[(int)$data[0]]??'';if($d->setBlockId($id)){$this->manager->syncModel($d);$p->sendMessage('§aBlock set to '.$d->getBlockId());}else$p->sendMessage('§cUnknown block ID.');});$p->sendForm($f);}
    private function exactForm(Player $p,DisplayEntity $d):void{$l=$d->getLocation();$c=[['type'=>'input','text'=>'X','default'=>(string)$l->x],['type'=>'input','text'=>'Y','default'=>(string)$l->y],['type'=>'input','text'=>'Z','default'=>(string)$l->z],['type'=>'input','text'=>'Pitch X','default'=>(string)$l->pitch],['type'=>'input','text'=>'Yaw Y','default'=>(string)$l->yaw],['type'=>'input','text'=>'Roll Z','default'=>(string)$d->getRoll()],['type'=>'input','text'=>'Size','default'=>(string)$d->getDisplaySize()]];$f=new SimpleForm(json_encode(['type'=>'custom_form','title'=>'Exact Values','content'=>$c]),function(Player $p,$data)use($d){if(!is_array($data))return;try{$x=(float)$data[0];$y=(float)$data[1];$z=(float)$data[2];$rx=(float)$data[3];$ry=(float)$data[4];$rz=(float)$data[5];$s=(float)$data[6];$d->teleport(new Location($x,$y,$z,$p->getWorld(),$ry,$rx));$d->setRoll($rz);$d->setDisplaySize($s);$this->manager->syncModel($d);$this->manager->refreshHandles($p);$p->sendMessage('§aExact values updated.');}catch(\Throwable $e){$p->sendMessage('§cInvalid values.');}});$p->sendForm($f);}
    public function command(Player $p,array $args):void{
        $sub=strtolower($args[0]??'help');switch($sub){case 'wand':$this->giveTools($p);break;case 'tools':$this->giveTools($p);break;case 'menu':$d=$this->manager->selected($p)??$this->manager->findInSight($p,[DisplayEntity::class]);if($d instanceof DisplayEntity)$this->openMenu($p,$d);else$p->sendMessage('§cNo display selected.');break;case 'spawn':$id=$args[1]??'stone';$pos=$p->getPosition()->addVector($p->getDirectionVector()->multiply(3));$d=new DisplayEntity(new Location($pos->x,$pos->y,$pos->z,$p->getWorld(),$p->getLocation()->yaw,$p->getLocation()->pitch),null);if(!$d->setBlockId($id)){$d->flagForDespawn();$p->sendMessage('§cUnknown block.');break;}$d->setMode(1);$d->spawnToAll();$this->manager->syncModel($d);$this->manager->select($p,$d);break;case 'control':$d=$this->manager->selected($p)??$this->manager->findInSight($p,[DisplayEntity::class]);if($d instanceof DisplayEntity){if($this->manager->hasPlayerControl($p))$this->manager->stopPlayerControl($p,true);else $this->manager->startPlayerControl($p,$d);}else$p->sendMessage('§cNo display selected.');break;case 'group':$this->openGroupMenu($p);break;case 'grouptool':$this->openGroupMenu($p);break;case 'mode':$m=strtolower($args[1]??'');$map=['off'=>0,'move'=>1,'rotate'=>2,'scale'=>3,'calibrate'=>4];$d=$this->manager->selected($p);if($d instanceof DisplayEntity&&isset($map[$m])){$this->manager->stopDrag($p);$this->manager->setMode($d,$map[$m]);$this->manager->refreshHandles($p);}else$p->sendMessage('§7Usage: /bdbd mode <off|move|rotate|scale|calibrate>');break;case 'drag':$action=strtolower($args[1]??'');if($action==='stop'){$this->manager->stopDrag($p);$p->sendMessage('§7Drag stopped.');}else{$p->sendMessage('§7Usage: /bdbd drag stop');}break;case 'solid':$d=$this->manager->selected($p);if($d instanceof DisplayEntity){$v=strtolower($args[1]??'toggle');$d->setSolid($v==='on'?true:($v==='off'?false:!$d->isSolid()));$this->manager->refreshClientSolidCollision($p);$p->sendMessage('§eWalkable / Solid: '.($d->isSolid()?'ON':'OFF').' §7(client-native collision, no world blocks)');}break;case 'delete':$d=$this->manager->selected($p);if($d instanceof DisplayEntity){$this->manager->deleteDisplay($d);$this->manager->clearSelection($p);}$p->sendMessage('§aDeleted selected display.');break;case 'cleanup':$this->manager->clearAllHandles();$n=0;foreach($this->getServer()->getWorldManager()->getWorlds() as $w)foreach($w->getEntities() as $e)if($e instanceof ModelFaceEntity && $e->isClosed()===false && $this->manager->findOwnerDisplay($e)===null){$e->flagForDespawn();$n++;}$p->sendMessage('§aCleanup complete: '.$n.' model entities.');break;case 'list':$list=$this->manager->listDisplays($p);$p->sendMessage('§eDisplays in world: '.count($list));foreach(array_slice($list,0,20) as $d)$p->sendMessage('§7#'.$d->getId().' '.$d->getBlockId().' size='.$d->getDisplaySize());break;case 'version':$p->sendMessage('§aBDBlockDisplay '.BD_VERSION.'');break;case 'debug':$p->sendMessage('§7Selected='.($this->manager->selected($p)?->getId()??'none').' Control='.($this->manager->hasPlayerControl($p)?'ON':'OFF').' Drag='.($this->manager->hasActiveDrag($p)?'ON':'OFF'));break;default:$p->sendMessage('§eBDBlockDisplay: /bdbd wand | spawn <block> | menu | control | group | grouptool | mode <move|rotate|scale|calibrate> | drag stop | solid [on|off|toggle] | delete | cleanup | list | version | debug');}}
}


final class Model_1_0_up extends ModelFaceEntity{ protected static string $NETWORK='bd:model_1_0_up'; }
final class Model_1_0_down extends ModelFaceEntity{ protected static string $NETWORK='bd:model_1_0_down'; }
final class Model_1_0_north extends ModelFaceEntity{ protected static string $NETWORK='bd:model_1_0_north'; }
final class Model_1_0_south extends ModelFaceEntity{ protected static string $NETWORK='bd:model_1_0_south'; }
final class Model_1_0_east extends ModelFaceEntity{ protected static string $NETWORK='bd:model_1_0_east'; }
final class Model_1_0_west extends ModelFaceEntity{ protected static string $NETWORK='bd:model_1_0_west'; }
final class Model_2_0_up extends ModelFaceEntity{ protected static string $NETWORK='bd:model_2_0_up'; }
final class Model_2_0_down extends ModelFaceEntity{ protected static string $NETWORK='bd:model_2_0_down'; }
final class Model_2_0_north extends ModelFaceEntity{ protected static string $NETWORK='bd:model_2_0_north'; }
final class Model_2_0_south extends ModelFaceEntity{ protected static string $NETWORK='bd:model_2_0_south'; }
final class Model_2_0_east extends ModelFaceEntity{ protected static string $NETWORK='bd:model_2_0_east'; }
final class Model_2_0_west extends ModelFaceEntity{ protected static string $NETWORK='bd:model_2_0_west'; }
final class Model_2_1_up extends ModelFaceEntity{ protected static string $NETWORK='bd:model_2_1_up'; }
final class Model_2_1_down extends ModelFaceEntity{ protected static string $NETWORK='bd:model_2_1_down'; }
final class Model_2_1_north extends ModelFaceEntity{ protected static string $NETWORK='bd:model_2_1_north'; }
final class Model_2_1_south extends ModelFaceEntity{ protected static string $NETWORK='bd:model_2_1_south'; }
final class Model_2_1_east extends ModelFaceEntity{ protected static string $NETWORK='bd:model_2_1_east'; }
final class Model_2_1_west extends ModelFaceEntity{ protected static string $NETWORK='bd:model_2_1_west'; }
final class Model_3_0_up extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_0_up'; }
final class Model_3_0_down extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_0_down'; }
final class Model_3_0_north extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_0_north'; }
final class Model_3_0_south extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_0_south'; }
final class Model_3_0_east extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_0_east'; }
final class Model_3_0_west extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_0_west'; }
final class Model_3_1_up extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_1_up'; }
final class Model_3_1_down extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_1_down'; }
final class Model_3_1_north extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_1_north'; }
final class Model_3_1_south extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_1_south'; }
final class Model_3_1_east extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_1_east'; }
final class Model_3_1_west extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_1_west'; }
final class Model_3_2_up extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_2_up'; }
final class Model_3_2_down extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_2_down'; }
final class Model_3_2_north extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_2_north'; }
final class Model_3_2_south extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_2_south'; }
final class Model_3_2_east extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_2_east'; }
final class Model_3_2_west extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_2_west'; }
final class Model_3_3_up extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_3_up'; }
final class Model_3_3_down extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_3_down'; }
final class Model_3_3_north extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_3_north'; }
final class Model_3_3_south extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_3_south'; }
final class Model_3_3_east extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_3_east'; }
final class Model_3_3_west extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_3_west'; }
final class Model_3_4_up extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_4_up'; }
final class Model_3_4_down extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_4_down'; }
final class Model_3_4_north extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_4_north'; }
final class Model_3_4_south extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_4_south'; }
final class Model_3_4_east extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_4_east'; }
final class Model_3_4_west extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_4_west'; }
final class Model_3_5_up extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_5_up'; }
final class Model_3_5_down extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_5_down'; }
final class Model_3_5_north extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_5_north'; }
final class Model_3_5_south extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_5_south'; }
final class Model_3_5_east extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_5_east'; }
final class Model_3_5_west extends ModelFaceEntity{ protected static string $NETWORK='bd:model_3_5_west'; }
final class Model_4_0_up extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_0_up'; }
final class Model_4_0_down extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_0_down'; }
final class Model_4_0_north extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_0_north'; }
final class Model_4_0_south extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_0_south'; }
final class Model_4_0_east extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_0_east'; }
final class Model_4_0_west extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_0_west'; }
final class Model_4_1_up extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_1_up'; }
final class Model_4_1_down extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_1_down'; }
final class Model_4_1_north extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_1_north'; }
final class Model_4_1_south extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_1_south'; }
final class Model_4_1_east extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_1_east'; }
final class Model_4_1_west extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_1_west'; }
final class Model_4_2_up extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_2_up'; }
final class Model_4_2_down extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_2_down'; }
final class Model_4_2_north extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_2_north'; }
final class Model_4_2_south extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_2_south'; }
final class Model_4_2_east extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_2_east'; }
final class Model_4_2_west extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_2_west'; }
final class Model_4_3_up extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_3_up'; }
final class Model_4_3_down extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_3_down'; }
final class Model_4_3_north extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_3_north'; }
final class Model_4_3_south extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_3_south'; }
final class Model_4_3_east extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_3_east'; }
final class Model_4_3_west extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_3_west'; }
final class Model_4_4_up extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_4_up'; }
final class Model_4_4_down extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_4_down'; }
final class Model_4_4_north extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_4_north'; }
final class Model_4_4_south extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_4_south'; }
final class Model_4_4_east extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_4_east'; }
final class Model_4_4_west extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_4_west'; }
final class Model_4_5_up extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_5_up'; }
final class Model_4_5_down extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_5_down'; }
final class Model_4_5_north extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_5_north'; }
final class Model_4_5_south extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_5_south'; }
final class Model_4_5_east extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_5_east'; }
final class Model_4_5_west extends ModelFaceEntity{ protected static string $NETWORK='bd:model_4_5_west'; }

final class ModelRegistryBootstrap{public static function register():void{
ModelEntityRegistry::add(1,0,'up',Model_1_0_up::class);
ModelEntityRegistry::add(1,0,'down',Model_1_0_down::class);
ModelEntityRegistry::add(1,0,'north',Model_1_0_north::class);
ModelEntityRegistry::add(1,0,'south',Model_1_0_south::class);
ModelEntityRegistry::add(1,0,'east',Model_1_0_east::class);
ModelEntityRegistry::add(1,0,'west',Model_1_0_west::class);
ModelEntityRegistry::add(2,0,'up',Model_2_0_up::class);
ModelEntityRegistry::add(2,0,'down',Model_2_0_down::class);
ModelEntityRegistry::add(2,0,'north',Model_2_0_north::class);
ModelEntityRegistry::add(2,0,'south',Model_2_0_south::class);
ModelEntityRegistry::add(2,0,'east',Model_2_0_east::class);
ModelEntityRegistry::add(2,0,'west',Model_2_0_west::class);
ModelEntityRegistry::add(2,1,'up',Model_2_1_up::class);
ModelEntityRegistry::add(2,1,'down',Model_2_1_down::class);
ModelEntityRegistry::add(2,1,'north',Model_2_1_north::class);
ModelEntityRegistry::add(2,1,'south',Model_2_1_south::class);
ModelEntityRegistry::add(2,1,'east',Model_2_1_east::class);
ModelEntityRegistry::add(2,1,'west',Model_2_1_west::class);
ModelEntityRegistry::add(3,0,'up',Model_3_0_up::class);
ModelEntityRegistry::add(3,0,'down',Model_3_0_down::class);
ModelEntityRegistry::add(3,0,'north',Model_3_0_north::class);
ModelEntityRegistry::add(3,0,'south',Model_3_0_south::class);
ModelEntityRegistry::add(3,0,'east',Model_3_0_east::class);
ModelEntityRegistry::add(3,0,'west',Model_3_0_west::class);
ModelEntityRegistry::add(3,1,'up',Model_3_1_up::class);
ModelEntityRegistry::add(3,1,'down',Model_3_1_down::class);
ModelEntityRegistry::add(3,1,'north',Model_3_1_north::class);
ModelEntityRegistry::add(3,1,'south',Model_3_1_south::class);
ModelEntityRegistry::add(3,1,'east',Model_3_1_east::class);
ModelEntityRegistry::add(3,1,'west',Model_3_1_west::class);
ModelEntityRegistry::add(3,2,'up',Model_3_2_up::class);
ModelEntityRegistry::add(3,2,'down',Model_3_2_down::class);
ModelEntityRegistry::add(3,2,'north',Model_3_2_north::class);
ModelEntityRegistry::add(3,2,'south',Model_3_2_south::class);
ModelEntityRegistry::add(3,2,'east',Model_3_2_east::class);
ModelEntityRegistry::add(3,2,'west',Model_3_2_west::class);
ModelEntityRegistry::add(3,3,'up',Model_3_3_up::class);
ModelEntityRegistry::add(3,3,'down',Model_3_3_down::class);
ModelEntityRegistry::add(3,3,'north',Model_3_3_north::class);
ModelEntityRegistry::add(3,3,'south',Model_3_3_south::class);
ModelEntityRegistry::add(3,3,'east',Model_3_3_east::class);
ModelEntityRegistry::add(3,3,'west',Model_3_3_west::class);
ModelEntityRegistry::add(3,4,'up',Model_3_4_up::class);
ModelEntityRegistry::add(3,4,'down',Model_3_4_down::class);
ModelEntityRegistry::add(3,4,'north',Model_3_4_north::class);
ModelEntityRegistry::add(3,4,'south',Model_3_4_south::class);
ModelEntityRegistry::add(3,4,'east',Model_3_4_east::class);
ModelEntityRegistry::add(3,4,'west',Model_3_4_west::class);
ModelEntityRegistry::add(3,5,'up',Model_3_5_up::class);
ModelEntityRegistry::add(3,5,'down',Model_3_5_down::class);
ModelEntityRegistry::add(3,5,'north',Model_3_5_north::class);
ModelEntityRegistry::add(3,5,'south',Model_3_5_south::class);
ModelEntityRegistry::add(3,5,'east',Model_3_5_east::class);
ModelEntityRegistry::add(3,5,'west',Model_3_5_west::class);
ModelEntityRegistry::add(4,0,'up',Model_4_0_up::class);
ModelEntityRegistry::add(4,0,'down',Model_4_0_down::class);
ModelEntityRegistry::add(4,0,'north',Model_4_0_north::class);
ModelEntityRegistry::add(4,0,'south',Model_4_0_south::class);
ModelEntityRegistry::add(4,0,'east',Model_4_0_east::class);
ModelEntityRegistry::add(4,0,'west',Model_4_0_west::class);
ModelEntityRegistry::add(4,1,'up',Model_4_1_up::class);
ModelEntityRegistry::add(4,1,'down',Model_4_1_down::class);
ModelEntityRegistry::add(4,1,'north',Model_4_1_north::class);
ModelEntityRegistry::add(4,1,'south',Model_4_1_south::class);
ModelEntityRegistry::add(4,1,'east',Model_4_1_east::class);
ModelEntityRegistry::add(4,1,'west',Model_4_1_west::class);
ModelEntityRegistry::add(4,2,'up',Model_4_2_up::class);
ModelEntityRegistry::add(4,2,'down',Model_4_2_down::class);
ModelEntityRegistry::add(4,2,'north',Model_4_2_north::class);
ModelEntityRegistry::add(4,2,'south',Model_4_2_south::class);
ModelEntityRegistry::add(4,2,'east',Model_4_2_east::class);
ModelEntityRegistry::add(4,2,'west',Model_4_2_west::class);
ModelEntityRegistry::add(4,3,'up',Model_4_3_up::class);
ModelEntityRegistry::add(4,3,'down',Model_4_3_down::class);
ModelEntityRegistry::add(4,3,'north',Model_4_3_north::class);
ModelEntityRegistry::add(4,3,'south',Model_4_3_south::class);
ModelEntityRegistry::add(4,3,'east',Model_4_3_east::class);
ModelEntityRegistry::add(4,3,'west',Model_4_3_west::class);
ModelEntityRegistry::add(4,4,'up',Model_4_4_up::class);
ModelEntityRegistry::add(4,4,'down',Model_4_4_down::class);
ModelEntityRegistry::add(4,4,'north',Model_4_4_north::class);
ModelEntityRegistry::add(4,4,'south',Model_4_4_south::class);
ModelEntityRegistry::add(4,4,'east',Model_4_4_east::class);
ModelEntityRegistry::add(4,4,'west',Model_4_4_west::class);
ModelEntityRegistry::add(4,5,'up',Model_4_5_up::class);
ModelEntityRegistry::add(4,5,'down',Model_4_5_down::class);
ModelEntityRegistry::add(4,5,'north',Model_4_5_north::class);
ModelEntityRegistry::add(4,5,'south',Model_4_5_south::class);
ModelEntityRegistry::add(4,5,'east',Model_4_5_east::class);
ModelEntityRegistry::add(4,5,'west',Model_4_5_west::class);
}
public static function all():array{return [[Model_1_0_up::class,'bd:model_1_0_up'],[Model_1_0_down::class,'bd:model_1_0_down'],[Model_1_0_north::class,'bd:model_1_0_north'],[Model_1_0_south::class,'bd:model_1_0_south'],[Model_1_0_east::class,'bd:model_1_0_east'],[Model_1_0_west::class,'bd:model_1_0_west'],[Model_2_0_up::class,'bd:model_2_0_up'],[Model_2_0_down::class,'bd:model_2_0_down'],[Model_2_0_north::class,'bd:model_2_0_north'],[Model_2_0_south::class,'bd:model_2_0_south'],[Model_2_0_east::class,'bd:model_2_0_east'],[Model_2_0_west::class,'bd:model_2_0_west'],[Model_2_1_up::class,'bd:model_2_1_up'],[Model_2_1_down::class,'bd:model_2_1_down'],[Model_2_1_north::class,'bd:model_2_1_north'],[Model_2_1_south::class,'bd:model_2_1_south'],[Model_2_1_east::class,'bd:model_2_1_east'],[Model_2_1_west::class,'bd:model_2_1_west'],[Model_3_0_up::class,'bd:model_3_0_up'],[Model_3_0_down::class,'bd:model_3_0_down'],[Model_3_0_north::class,'bd:model_3_0_north'],[Model_3_0_south::class,'bd:model_3_0_south'],[Model_3_0_east::class,'bd:model_3_0_east'],[Model_3_0_west::class,'bd:model_3_0_west'],[Model_3_1_up::class,'bd:model_3_1_up'],[Model_3_1_down::class,'bd:model_3_1_down'],[Model_3_1_north::class,'bd:model_3_1_north'],[Model_3_1_south::class,'bd:model_3_1_south'],[Model_3_1_east::class,'bd:model_3_1_east'],[Model_3_1_west::class,'bd:model_3_1_west'],[Model_3_2_up::class,'bd:model_3_2_up'],[Model_3_2_down::class,'bd:model_3_2_down'],[Model_3_2_north::class,'bd:model_3_2_north'],[Model_3_2_south::class,'bd:model_3_2_south'],[Model_3_2_east::class,'bd:model_3_2_east'],[Model_3_2_west::class,'bd:model_3_2_west'],[Model_3_3_up::class,'bd:model_3_3_up'],[Model_3_3_down::class,'bd:model_3_3_down'],[Model_3_3_north::class,'bd:model_3_3_north'],[Model_3_3_south::class,'bd:model_3_3_south'],[Model_3_3_east::class,'bd:model_3_3_east'],[Model_3_3_west::class,'bd:model_3_3_west'],[Model_3_4_up::class,'bd:model_3_4_up'],[Model_3_4_down::class,'bd:model_3_4_down'],[Model_3_4_north::class,'bd:model_3_4_north'],[Model_3_4_south::class,'bd:model_3_4_south'],[Model_3_4_east::class,'bd:model_3_4_east'],[Model_3_4_west::class,'bd:model_3_4_west'],[Model_3_5_up::class,'bd:model_3_5_up'],[Model_3_5_down::class,'bd:model_3_5_down'],[Model_3_5_north::class,'bd:model_3_5_north'],[Model_3_5_south::class,'bd:model_3_5_south'],[Model_3_5_east::class,'bd:model_3_5_east'],[Model_3_5_west::class,'bd:model_3_5_west'],[Model_4_0_up::class,'bd:model_4_0_up'],[Model_4_0_down::class,'bd:model_4_0_down'],[Model_4_0_north::class,'bd:model_4_0_north'],[Model_4_0_south::class,'bd:model_4_0_south'],[Model_4_0_east::class,'bd:model_4_0_east'],[Model_4_0_west::class,'bd:model_4_0_west'],[Model_4_1_up::class,'bd:model_4_1_up'],[Model_4_1_down::class,'bd:model_4_1_down'],[Model_4_1_north::class,'bd:model_4_1_north'],[Model_4_1_south::class,'bd:model_4_1_south'],[Model_4_1_east::class,'bd:model_4_1_east'],[Model_4_1_west::class,'bd:model_4_1_west'],[Model_4_2_up::class,'bd:model_4_2_up'],[Model_4_2_down::class,'bd:model_4_2_down'],[Model_4_2_north::class,'bd:model_4_2_north'],[Model_4_2_south::class,'bd:model_4_2_south'],[Model_4_2_east::class,'bd:model_4_2_east'],[Model_4_2_west::class,'bd:model_4_2_west'],[Model_4_3_up::class,'bd:model_4_3_up'],[Model_4_3_down::class,'bd:model_4_3_down'],[Model_4_3_north::class,'bd:model_4_3_north'],[Model_4_3_south::class,'bd:model_4_3_south'],[Model_4_3_east::class,'bd:model_4_3_east'],[Model_4_3_west::class,'bd:model_4_3_west'],[Model_4_4_up::class,'bd:model_4_4_up'],[Model_4_4_down::class,'bd:model_4_4_down'],[Model_4_4_north::class,'bd:model_4_4_north'],[Model_4_4_south::class,'bd:model_4_4_south'],[Model_4_4_east::class,'bd:model_4_4_east'],[Model_4_4_west::class,'bd:model_4_4_west'],[Model_4_5_up::class,'bd:model_4_5_up'],[Model_4_5_down::class,'bd:model_4_5_down'],[Model_4_5_north::class,'bd:model_4_5_north'],[Model_4_5_south::class,'bd:model_4_5_south'],[Model_4_5_east::class,'bd:model_4_5_east'],[Model_4_5_west::class,'bd:model_4_5_west']];}}

final class BDBlockCommand extends \pocketmine\command\Command{
    public function __construct(private main $plugin){parent::__construct('bdbd','BDBlockDisplay editor','/bdbd <subcommand>');$this->setPermission('bdbd.use');}
    public function execute(\pocketmine\command\CommandSender $sender,string $label,array $args):bool{if(!$sender instanceof Player){$sender->sendMessage('Use this command in-game.');return true;} $this->plugin->command($sender,$args);return true;}
}
