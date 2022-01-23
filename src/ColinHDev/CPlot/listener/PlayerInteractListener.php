<?php

namespace ColinHDev\CPlot\listener;

use ColinHDev\CPlot\provider\DataProvider;
use ColinHDev\CPlot\ResourceManager;
use ColinHDev\CPlotAPI\attributes\BlockListAttribute;
use ColinHDev\CPlotAPI\attributes\BooleanAttribute;
use ColinHDev\CPlotAPI\plots\BasePlot;
use ColinHDev\CPlotAPI\plots\flags\FlagIDs;
use ColinHDev\CPlotAPI\plots\Plot;
use ColinHDev\CPlotAPI\worlds\WorldSettings;
use pocketmine\block\Block;
use pocketmine\block\Door;
use pocketmine\block\FenceGate;
use pocketmine\block\Trapdoor;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use Ramsey\Uuid\Uuid;

class PlayerInteractListener implements Listener {

    public function onPlayerInteract(PlayerInteractEvent $event) : void {
        if ($event->isCancelled()) {
            return;
        }

        $position = $event->getBlock()->getPosition();
        $worldSettings = DataProvider::getInstance()->loadWorldIntoCache($position->getWorld()->getFolderName());
        if ($worldSettings === null) {
            $event->getPlayer()->sendMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("player.interact.worldNotLoaded"));
            $event->cancel();
            return;
        }
        if (!$worldSettings instanceof WorldSettings) {
            return;
        }

        $plot = Plot::loadFromPositionIntoCache($position);
        if ($plot instanceof BasePlot && !$plot instanceof Plot) {
            $event->getPlayer()->sendMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("player.interact.plotNotLoaded"));
            $event->cancel();
            return;
        }
        if ($plot !== null) {
            $player = $event->getPlayer();
            if ($player->hasPermission("cplot.interact.plot")) {
                return;
            }

            $playerUUID = $player->getUniqueId()->toString();
            if ($plot->isPlotOwner($playerUUID)) {
                return;
            }
            if ($plot->isPlotTrusted($playerUUID)) {
                return;
            }
            if ($plot->isPlotHelper($playerUUID)) {
                foreach ($plot->getPlotOwners() as $plotOwner) {
                    $owner = $player->getServer()->getPlayerByUUID(Uuid::fromString($plotOwner->getPlayerUUID()));
                    if ($owner !== null) {
                        return;
                    }
                }
            }

            $block = $event->getBlock();
            /** @var BooleanAttribute $flag */
            $flag = $plot->getFlagNonNullByID(FlagIDs::FLAG_PLAYER_INTERACT);
            if ($flag->getValue() === true) {
                if ($block instanceof Door || $block instanceof Trapdoor || $block instanceof FenceGate) {
                    return;
                }
            }
            /** @var BlockListAttribute $flag */
            $flag = $plot->getFlagNonNullByID(FlagIDs::FLAG_USE);
            /** @var Block $value */
            foreach ($flag->getValue() as $value) {
                if ($block->isSameType($value)) {
                    return;
                }
            }

        } else {
            if ($event->getPlayer()->hasPermission("cplot.interact.road")) {
                return;
            }
        }

        $event->cancel();
    }
}