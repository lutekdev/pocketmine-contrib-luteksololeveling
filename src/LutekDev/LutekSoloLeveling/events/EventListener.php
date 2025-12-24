<?php

declare( strict_types = 1 );

namespace LutekDev\LutekSoloLeveling\events;

use LutekDev\LutekSoloLeveling\forms\SkillForm;
use LutekDev\LutekSoloLeveling\Main;
use pocketmine\entity\animation\HurtAnimation;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\ItemTypeIds;
use pocketmine\player\Player;
use pocketmine\world\particle\CriticalParticle;

readonly class EventListener implements Listener {
    public function __construct ( private Main $plugin ) {}
    
    // Gerencia a criação da Sessão
    public function onJoin ( PlayerJoinEvent $event ) : void {
        $this->plugin->createSession( $event->getPlayer() );
    }
    
    public function onQuit ( PlayerQuitEvent $event ) : void {
        $session = $this->plugin->getSession( $event->getPlayer() );
        $session?->save();
        $this->plugin->removeSession( $event->getPlayer() );
    }
    
    /**
     * Sistema de Livro de Skills
     */
    public function onInteract ( PlayerItemUseEvent $event ) : void {
        $player = $event->getPlayer();
        $item = $event->getItem();
        
        // Verifica se é um livro e se tem o nome específico
        if ($item->getTypeId() === ItemTypeIds::BOOK && $item->getCustomName() === "§r§dGrimório de Skills") {
            $session = $this->plugin->getSession( $player );
            if ($session !== null) {
                // Abre a UI
                $player->sendForm( new SkillForm( $session ) );
            }
        }
    }
    
    /**
     * Sistema de Cansaço por Movimento
     */
    public function onMove ( PlayerMoveEvent $event ) : void {
        $player = $event->getPlayer();
        
        // Otimização: Se o jogador não se moveu X/Y/Z (apenas girou a câmera), ignora.
        $from = $event->getFrom();
        $to = $event->getTo();
        
        // Usamos distanceSquared() porque é muito mais rápido que distance() para a CPU
        $distSq = $from->distanceSquared( $to );
        
        // Se moveu menos que 0.01 blocos (muito pouco), ignora
        if ($distSq < 0.0001) {
            return;
        }
        
        $session = $this->plugin->getSession( $player );
        if ($session !== null) {
            $config = $this->plugin->getConfig()->get( "gameplay" );
            
            // Detecta se está correndo ou andando
            $isSprinting = $player->isSprinting();
            
            // Calcula o custo base
            $cost = $isSprinting ? $config[ 'fatigue_cost_sprint' ] : $config[ 'fatigue_cost_walk' ];
            
            // Aplica a fadiga (mas não recalcula status a cada passo para não lagar)
            $session->addFatiguePassive( $cost );
        }
    }
    
    // Aplica o Dano (Strength) e Defesa (Defense)
    public function onDamage ( EntityDamageEvent $event ) : void {
        // Se o evento foi cancelado por outro motivo, ignora
        if ($event->isCancelled()) return;
        
        $entity = $event->getEntity();
        
        // 1. CÁLCULO DE DEFESA (Quando o JOGADOR apanha)
        if ($entity instanceof Player) {
            $session = $this->plugin->getSession( $entity );
            
            if ($session !== null) {
                // Cancela o evento vanilla para o Minecraft não baixar os corações automaticamente
                $event->cancel();
                
                $finalDamage = $event->getFinalDamage();
                $died = $session->applyDamage( $finalDamage );
                
                $entity->broadcastAnimation( new HurtAnimation( $entity ) );
                
                if ($died) {
                    $entity->kill();
                }
            }
            return; // Encerra aqui se a vítima for player
        }
        
        
        // 2. CÁLCULO DE ATAQUE (Quando o JOGADOR bate)
        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            
            if ($damager instanceof Player && $entity instanceof Living) {
                $session = $this->plugin->getSession( $damager );
                
                if ($session !== null) {
                    $damage = $session->getAttackDamage();
                    
                    // SISTEMA DE  CRÍTICO
                    if (mt_rand( 0, 100 ) < $session->getCritChance()) {
                        $damage *= 1.5;
                        
                        // Efeitos Visuais (Partículas)
                        // Gera partículas ao redor da entidade atingida
                        $entity->getWorld()->addParticle( $entity->getPosition()->add( 0, 1, 0 ), new CriticalParticle() );
                        
                        // Feedback Sonoro (Som metálico pesado)
                        $damager->getNetworkSession()->sendDataPacket( \pocketmine\network\mcpe\protocol\PlaySoundPacket::create( "random.anvil_land", $entity->getPosition()->x, $entity->getPosition()->y, $entity->getPosition()->z, 0.5, 2.0 // Volume, Pitch (Agudo)
                        ) );
                        
                        $damager->sendTip( "§c§lCRÍTICO!" );
                    }
                    
                    $event->setBaseDamage( $damage );
                    $session->addFatigue( 0.5 );
                }
            }
        }
    }
}