<?php

declare( strict_types = 1 );

namespace LutekDev\LutekSoloLeveling;

use LutekDev\LutekSoloLeveling\commands\AdminRPGCommand;
use LutekDev\LutekSoloLeveling\core\PlayerSession;
use LutekDev\LutekSoloLeveling\database\Database;
use LutekDev\LutekSoloLeveling\events\EventListener;
use LutekDev\LutekSoloLeveling\task\SystemTask;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;

class Main extends PluginBase {
    use SingletonTrait;
    
    /** @var PlayerSession[] */
    private array $sessions = [];
    
    protected function onLoad () : void {
        self::setInstance( $this );
    }
    
    protected function onEnable () : void {
        new Database( $this );
        
        // Salva a config.yml padrão se não existir
        $this->saveDefaultConfig();
        
        // Registra os Eventos
        $this->getServer()->getPluginManager()->registerEvents( new EventListener( $this ), $this );
        
        // Registro de Comandos
        $this->getServer()->getCommandMap()->register( "sololeveling", new AdminRPGCommand( $this ) );
        
        // Inicia o Loop do Sistema (1 segundo = 20 ticks)
        $this->getScheduler()->scheduleRepeatingTask( new SystemTask( $this ), 20 );
    }
    
    protected function onDisable () : void {
        // Salvar dados de todos os jogadores online antes de desligar
        foreach ($this->sessions as $session) {
        
        }
        $this->sessions = [];
    }
    
    // Cria ou carrega uma sessão para o jogador
    public function createSession ( Player $player ) : void {
        $uuid = $player->getUniqueId()->toString();
        if (!isset( $this->sessions[ $uuid ] )) {
            $this->sessions[ $uuid ] = new PlayerSession( $player, $this );
        }
    }
    
    // Remove a sessão (ao sair do servidor)
    public function removeSession ( Player $player ) : void {
        $uuid = $player->getUniqueId()->toString();
        
        if (isset( $this->sessions[ $uuid ] )) {
            unset( $this->sessions[ $uuid ] );
        }
    }
    
    // Retorna a sessão do jogador (Pode ser null se algo der errado)
    public function getSession ( Player $player ) : ?PlayerSession {
        $uuid = $player->getUniqueId()->toString();
        return $this->sessions[ $uuid ] ?? null;
    }
}