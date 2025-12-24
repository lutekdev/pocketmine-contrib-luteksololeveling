<?php

declare( strict_types = 1 );

namespace LutekDev\LutekSoloLeveling\commands;

use LutekDev\LutekSoloLeveling\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class AdminRPGCommand extends Command {
    public function __construct ( readonly private Main $plugin ) {
        parent::__construct( "setrpg", "Define status de RPG para testes", "/setrpg <stat> <valor>" );
        $this->setPermission( "sololeveling.admin" );
    }
    
    public function execute ( CommandSender $sender, string $commandLabel, array $args ) : void {
        if (!$sender instanceof Player) return;
        if (count( $args ) < 2) {
            $sender->sendMessage( "§cUse: /setrpg <str|vit|agi|int|def> <valor>" );
            return;
        }
        
        $stat = strtolower( $args[ 0 ] );
        $val = (int) $args[ 1 ];
        
        $session = $this->plugin->getSession( $sender );
        if ($session === null) return;
        
        $session->setAttribute( $stat, $val );
        $sender->sendMessage( "§aAtributo $stat definido para $val!" );
        $sender->sendMessage( "§7Verifique sua Scoreboard ou status." );
        
        $dash = new \LutekDev\LutekSoloLeveling\skills\list\DashSkill();
        $dash->cast( $sender, $session );
    }
}