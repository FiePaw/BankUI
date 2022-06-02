<?php

namespace FiePaw\BankUI;

use FiePaw\BankUI\InterestTask;
use onebone\economyapi\EconomyAPI;
use FiePaw\BankUI\libs\jojoe77777\FormAPI\SimpleForm;
use FiePaw\BankUI\libs\jojoe77777\FormAPI\CustomForm;

use pocketmine\block\Block;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\Server;
use pocketmine\player\Player;

use pocketmine\plugin\PluginBase;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;

use pocketmine\utils\Config;


class BankUI extends PluginBase implements Listener{

    private static $instance;
    public $player;
    public $playerList = [];

    public function onEnable(): void
    {
		$this->getLogger()->info("§aPlugin Aktif!! §eMade By FiePaw");
        $this->saveDefaultConfig();
        self::$instance = $this;
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        if (!file_exists($this->getDataFolder() . "Players")){
            mkdir($this->getDataFolder() . "Players");
        }
        date_default_timezone_set($this->getConfig()->get("timezone"));
        if ($this->getConfig()->get("enable-interest") == true) {
            $this->getScheduler()->scheduleRepeatingTask(new InterestTask($this), 1100);
        }
    }

    public function dailyInterest(){
        if (date("H:i") === "12:00"){
            foreach (glob($this->getDataFolder() . "Players/*.yml") as $players) {
                $playerBankMoney = new Config($players);
                //$player = basename($players, ".yml");
                $interest = ($this->getConfig()->get("interest-rates") / 100 * $playerBankMoney->get("Money"));
                $playerBankMoney->set("Money", round($playerBankMoney->get("Money") + $interest));
                $playerBankMoney->save();
                if ($playerBankMoney->get('Transactions') === 0){
                    $playerBankMoney->set('Transactions', date("§b[d/m/y]") . "§e - §aInterest $" . round($interest) . "\n");
                }
                else {
                    $playerBankMoney->set('Transactions', $playerBankMoney->get('Transactions') . date("§b[d/m/y]") . "§e - §a$" . round($interest) . " from interest" . "\n");
                }
                $playerBankMoney->save();
            }
            foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayers){
                $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $onlinePlayers->getName() . ".yml", Config::YAML);
                $onlinePlayers->sendMessage("§aYou have earned $" . round(($this->getConfig()->get("interest-rates") / 100) * $playerBankMoney->get("Money")) . " from bank interest");
            }
        }
    }

    public function onJoin(PlayerJoinEvent $event){
        $player = $event->getPlayer();
        if (!file_exists($this->getDataFolder() . "Players/" . $player->getName() . ".yml")) {
            new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML, array(
                "Money" => 0,
                "Transactions" => 0,
            ));
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
        switch($command->getName()){
            case "bank":
                if($sender instanceof Player){
                    if (isset($args[0]) && $sender->hasPermission("bankui.admin") || isset($args[0]) && $sender->isOp()){
                        if (!file_exists($this->getDataFolder() . "Players/" . $args[0] . ".yml")){
                            $sender->sendMessage("§c§lError: §r§aThis player does not have a bank account");
                            return true;
                        }
                        $this->otherTransactionsForm($sender, $args[0]);
                        return true;
                    }
                    $this->bankForm($sender);
                }
        }
        return true;
    }

    public function bankForm($player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
//        $api = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
//        $form = $api->createSimpleForm(function (Player $player, int $data = null) {
        $form = new SimpleForm(function (Player $player, int $data = null){
            $result = $data;
            if ($result === null) {
                return true;
            }
            switch ($result) {
                case 0;
                    $this->withdrawForm($player);
            }
            switch ($result) {
                case 1;
                    $this->depositForm($player);
            }
            switch ($result) {
                case 2;
                    $this->transferCustomForm($player);
            }
            switch ($result) {
                case 3;
                    $this->transactionsForm($player);
            }
        });

        $form->setTitle("§lBank BCA");
        $form->setContent("Uang di Bank: Rp" . $playerBankMoney->get("Money"));
        $form->addButton("§lTarik uang\n§r§dKlik untuk tarik...",0,"textures/ui/icon_book_writable");
        $form->addButton("§lMenyimpan uang\n§r§dKlik untuk menyimpan...",0,"textures/items/map_filled");
        $form->addButton("§lTransfer uang\n§r§dKlik untuk transfer...",0,"textures/ui/FriendsIcon");
//        $form->addButton("§lRiwayat\n§r§dClick to transfer...",0,"textures/ui/inventory_icon");
//        $form->addButton("§lRiwayat\n§r§dClick to transfer...",0,"textures/ui/invite_base");
        $form->addButton("§lRiwayat\n§r§dKlik untuk membuka...",0,"textures/ui/lock_color");
        $form->addButton("§l§cEXIT\n§r§dKlik untuk menutup...",0,"textures/ui/cancel");
        $form->sendtoPlayer($player);
        return $form;
    }

    public function withdrawForm($player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
//        $api = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
//        $form = $api->createSimpleForm(function (Player $player, int $data = null) {
        $form = new SimpleForm(function (Player $player, int $data = null){
            $result = $data;
            if ($result === null) {
                return true;
            }
            switch ($result) {
                case 0;
                    $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
                    if ($playerBankMoney->get("Money") == 0){
                        $player->sendMessage("§aKamu tidak punya uanf di bank untuk di tarik");
                        return true;
                    }
                    EconomyAPI::getInstance()->addMoney($player, $playerBankMoney->get("Money"));
                    $player->sendMessage("§aBerhasil tarik Rp" . $playerBankMoney->get("Money") . " dari bank");
                    if ($playerBankMoney->get('Transactions') === 0){
                        $playerBankMoney->set('Transactions', date("§b[d/m/y]") . "§e - §aWithdrew $" . $playerBankMoney->get("Money") . "\n");
                    }
                    else {
                        $playerBankMoney->set('Transactions', $playerBankMoney->get('Transactions') . date("§b[d/m/y]") . "§e - §aWithdrew $" . $playerBankMoney->get("Money") . "\n");
                    }
                    $playerBankMoney->set("Money", 0);
                    $playerBankMoney->save();
            }
            switch ($result) {
                case 1;
                    $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
                    if ($playerBankMoney->get("Money") == 0){
                        $player->sendMessage("§aKamu tidak punya uang");
                        return true;
                    }
                    EconomyAPI::getInstance()->addMoney($player, $playerBankMoney->get("Money") / 2);
                    $player->sendMessage("§aBerhasil tarik Rp" . $playerBankMoney->get("Money") /2 . " dari bank");
                    if ($playerBankMoney->get('Transactions') === 0){
                        $playerBankMoney->set('Transactions', date("§b[d/m/y]") . "§e - §aWithdrew $" . $playerBankMoney->get("Money") / 2 . "\n");
                    }
                    else {
                        $playerBankMoney->set('Transactions', $playerBankMoney->get('Transactions') . date("§b[d/m/y]") . "§e - §aWithdrew $" . $playerBankMoney->get("Money") / 2 . "\n");
                    }
                    $playerBankMoney->set("Money", $playerBankMoney->get("Money") / 2);
                    $playerBankMoney->save();
            }
            switch ($result) {
                case 2;
                    $this->withdrawCustomForm($player);
            }
        });

        $form->setTitle("§lPenarikan uang");
        $form->setContent("Uang di bank: Rp" . $playerBankMoney->get("Money"));
        $form->addButton("§lTarik semua\n§r§dClick to use...",0,"textures/ui/icon_book_writable");
        $form->addButton("§lTarik setengah\n§r§dClick to use...",0,"textures/ui/icon_book_writable");
        $form->addButton("§lPenarikan custom\n§r§dClick to use...",0,"textures/ui/icon_book_writable");
        $form->addButton("§l§cEXIT\n§r§dClick to close...",0,"textures/ui/cancel");
        $form->sendtoPlayer($player);
        return $form;
    }

    public function withdrawCustomForm($player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
//        $api = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
//        $form = $api->createCustomForm(function (Player $player, array $data = null) {
        $form = new CustomForm(function (Player $player, $data) {
            $result = $data;
            if ($result === null) {
                return true;
            }

            $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
            if ($playerBankMoney->get("Money") == 0){
                $player->sendMessage("§aKamu tidak memiliki uang untuk di tarik");
                return true;
            }
            if ($playerBankMoney->get("Money") < $data[1]){
                $player->sendMessage("§aKamu tidak memiliki banyak uang untuk di tarik Rp" . $data[1]);
                return true;
            }
            if (!is_numeric($data[1])){
                $player->sendMessage("§aMasukan angka yang benar!");
                return true;
            }
            if ($data[1] <= 0){
                $player->sendMessage("§aYou must enter an amount greater than 0");
                return true;
            }
            EconomyAPI::getInstance()->addMoney($player, $data[1]);
            $player->sendMessage("§aBerhasil menarik Rp" . $data[1] . " dari Bank");
            if ($playerBankMoney->get('Transactions') === 0){
                $playerBankMoney->set('Transactions', date("§b[d/m/y]") . "§e - §aWithdrew $" . $data[1] . "\n");
            }
            else {
                $playerBankMoney->set('Transactions', $playerBankMoney->get('Transactions') . date("§b[d/m/y]") . "§e - §aWithdrew $" . $data[1] . "\n");
            }
            $playerBankMoney->set("Money", $playerBankMoney->get("Money") - $data[1]);
            $playerBankMoney->save();
        });

        $form->setTitle("§lPenarikan");
        $form->addLabel("Uang di bank: Rp" . $playerBankMoney->get("Money"));
        $form->addInput("§rMasukan angka max", "100000");
        $form->sendtoPlayer($player);
        return $form;
    }


    public function depositForm($player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
//        $api = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
//        $form = $api->createSimpleForm(function (Player $player, int $data = null) {
        $form = new SimpleForm(function (Player $player, int $data = null){
            $result = $data;
            if ($result === null) {
                return true;
            }
            switch ($result) {
                case 0;
                    $playerMoney = EconomyAPI::getInstance()->myMoney($player);
                    $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
                    if ($playerMoney == 0){
                        $player->sendMessage("§aKamu tidak memiliki cukup uang untuk disimpan");
                        return true;
                    }
                    if ($playerBankMoney->get('Transactions') === 0){
                        $playerBankMoney->set('Transactions', date("§b[d/m/y]") . "§e - §aDeposited $" . $playerMoney . "\n");
                    }
                    else {
                        $playerBankMoney->set('Transactions', $playerBankMoney->get('Transactions') . date("§b[d/m/y]") . "§e - §aDeposited $" . $playerMoney . "\n");
                    }
                    $playerBankMoney->set("Money", $playerBankMoney->get("Money") + $playerMoney);
                    $player->sendMessage("§aBerhasil menyimpan Rp" . $playerMoney . " ke Bank");
                    EconomyAPI::getInstance()->reduceMoney($player, $playerMoney);
                    $playerBankMoney->save();
            }
            switch ($result) {
                case 1;
                    $playerMoney = EconomyAPI::getInstance()->myMoney($player);
                    $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
                    if ($playerMoney == 0){
                        $player->sendMessage("§aKamu tidak punya banyak uang untuk di simpan");
                        return true;
                    }
                    if ($playerBankMoney->get('Transactions') === 0){
                        $playerBankMoney->set('Transactions', date("§b[d/m/y]") . "§e - §aDeposited $" . $playerMoney / 2 . "\n");
                    }
                    else {
                        $playerBankMoney->set('Transactions', $playerBankMoney->get('Transactions') . date("§b[d/m/y]") . "§e - §aDeposited $" . $playerMoney / 2 . "\n");
                    }
                    $playerBankMoney->set("Money", $playerBankMoney->get("Money") + ($playerMoney / 2));
                    $player->sendMessage("§aBerhasil menyimpan Rp" . $playerMoney / 2 . " ke Bank");
                    EconomyAPI::getInstance()->reduceMoney($player, $playerMoney / 2);
                    $playerBankMoney->save();
            }
            switch ($result) {
                case 2;
                    $this->depositCustomForm($player);
            }
        });

        $form->setTitle("§lPentimpanan");
        $form->setContent("Uang di bank: Rp" . $playerBankMoney->get("Money"));
        $form->addButton("§lSimpan semua\n§r§dClick to use...",0,"textures/items/map_filled");
        $form->addButton("§lSimpan setengah\n§r§dClick to use...",0,"textures/items/map_filled");
        $form->addButton("§lSimpan Custom\n§r§dClick to use...",0,"textures/items/map_filled");
        $form->addButton("§l§cEXIT\n§r§dClick to close...",0,"textures/ui/cancel");
        $form->sendtoPlayer($player);
        return $form;
    }

    public function depositCustomForm($player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
//        $api = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
//        $form = $api->createCustomForm(function (Player $player, array $data = null) {
        $form = new CustomForm(function (Player $player, $data) {
            $result = $data;
            if ($result === null) {
                return true;
            }
            $playerMoney = EconomyAPI::getInstance()->myMoney($player);
            $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
//            if ($playerMoney == 0){
//                $player->sendMessage("§aYou do not have enough money to deposit into the bank");
//                return true;
//            }
            if ($playerMoney < $data[1]){
                $player->sendMessage("§aKamu tidak memiliki cukup uang untuk disimpan" . $data[1] . " ke Bank");
                return true;
            }
            if (!is_numeric($data[1])){
                $player->sendMessage("§aMasukan angka yang benar");
                return true;
            }
            if ($data[1] <= 0){
                $player->sendMessage("§aMasukan angka mulai dari 0");
                return true;
            }
            $player->sendMessage("§aBerhasil mentimpan Rp" . $data[1] . " ke Bank");
            if ($playerBankMoney->get('Transactions') === 0){
                $playerBankMoney->set('Transactions', date("§b[d/m/y]") . "§e - §aDeposited $" . $data[1] . "\n");
            }
            else {
                $playerBankMoney->set('Transactions', $playerBankMoney->get('Transactions') . date("§b[d/m/y]") . "§e - §aDeposited $" . $data[1] . "\n");
            }
            $playerBankMoney->set("Money", $playerBankMoney->get("Money") + $data[1]);
            EconomyAPI::getInstance()->reduceMoney($player, $data[1]);
            $playerBankMoney->save();
        });

        $form->setTitle("§lPentimpanan");
        $form->addLabel("Uang: Rp" . $playerBankMoney->get("Money"));
        $form->addInput("§rMasukan angka max", "100000");
        $form->sendtoPlayer($player);
        return $form;
    }

    public function transferCustomForm($player)
    {

        $list = [];
        foreach ($this->getServer()->getOnlinePlayers() as $players){
            if ($players->getName() !== $player->getName()) {
                $list[] = $players->getName();
            }
        }
        $this->playerList[$player->getName()] = $list;

        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
//        $api = Server::getInstance()->getPluginManager()->getPlugin("FormAPI");
//        $form = $api->createCustomForm(function (Player $player, array $data = null) {
        $form = new CustomForm(function (Player $player, $data) {
            $result = $data;
            if ($result === null) {
                return true;
            }

            if (!isset($this->playerList[$player->getName()][$data[1]])){
                $player->sendMessage("§aKamu harus memilih player yang benar");
                return true;
            }

            $index = $data[1];
            $playerName = $this->playerList[$player->getName()][$index];

            $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
            $otherPlayerBankMoney = new Config($this->getDataFolder() . "Players/" . $playerName . ".yml", Config::YAML);
            if ($playerBankMoney->get("Money") == 0){
                $player->sendMessage("§aKamu tidak memiliki uang di bank");
                return true;
            }
            if ($playerBankMoney->get("Money") < $data[2]){
                $player->sendMessage("§aKamu tidak memiliki cukup uang untuk ditransfer sebesar Rp" . $data[2]);
                return true;
            }
            if (!is_numeric($data[2])){
                $player->sendMessage("§aMasukan angka");
                return true;
            }
            if ($data[2] <= 0){
                $player->sendMessage("§aKamu harus mengirim minimal Rp.1");
                return true;
            }
            $player->sendMessage("§aBerhasil mentransfer Rp" . $data[2] . " into " . $playerName . "'s ke Bank lain");
            if ($this->getServer()->getPlayer($playerName)) {
                $otherPlayer = $this->getServer()->getPlayer($playerName);
                $otherPlayer->sendMessage("§a" . $player->getName() . " transfer Rp" . $data[2] . " ke Bank kamu");
            }
            if ($playerBankMoney->get('Transactions') === 0){
                $playerBankMoney->set('Transactions', date("§b[d/m/y]") . "§e - §aTransferred $" . $data[2] . " into " . $playerName . "'s bank account" . "\n");
                $otherPlayerBankMoney->set('Transactions', date("§b[d/m/y]") . "§e - §a" . $player->getName() . " Transferred $" . $data[2] . " into your bank account" . "\n");
            }
            else {
                $otherPlayerBankMoney->set('Transactions', $playerBankMoney->get('Transactions') . date("§b[d/m/y]") . "§e - §a" . $player->getName() . " Transferred $" . $data[2] . " into your bank account" . "\n");
                $playerBankMoney->set('Transactions', $playerBankMoney->get('Transactions') . date("§b[d/m/y]") . "§e - §aTransferred $" . $data[2] . " into " . $playerName . "'s bank account" . "\n");
            }
            $playerBankMoney->set("Money", $playerBankMoney->get("Money") - $data[2]);
            $otherPlayerBankMoney->set("Money", $otherPlayerBankMoney->get("Money") + $data[2]);
            $playerBankMoney->save();
            $otherPlayerBankMoney->save();
            });


        $form->setTitle("§lTransfer");
        $form->addLabel("Uang di bank: Rp" . $playerBankMoney->get("Money"));
        $form->addDropdown("Pilih player", $this->playerList[$player->getName()]);
        $form->addInput("§rMasukan jumlah max", "100000");
        $form->sendtoPlayer($player);
        return $form;
    }

    public function transactionsForm($player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player->getName() . ".yml", Config::YAML);
        $playerMoney = EconomyAPI::getInstance()->myMoney($player);
        $form = new SimpleForm(function (Player $player, int $data = null){
            $result = $data;
            if ($result === null) {
                return true;
            }
        });

        $form->setTitle("§lTransfer");
        if ($playerBankMoney->get('Transactions') === 0){
            $form->setContent("You have not made any transactions yet");
        }
        else {
            $form->setContent($playerBankMoney->get("Transactions"));
        }
        $form->addButton("§l§cEXIT\n§r§dClick to close...",0,"textures/ui/cancel");
        $form->sendtoPlayer($player);
        return $form;
    }

    public function otherTransactionsForm($sender, $player)
    {
        $playerBankMoney = new Config($this->getDataFolder() . "Players/" . $player . ".yml", Config::YAML);
        $form = new SimpleForm(function (Player $player, int $data = null){
            $result = $data;
            if ($result === null) {
                return true;
            }
        });

        $form->setTitle("§l" . $player . "'s Transactions");
        if ($playerBankMoney->get('Transactions') === 0){
            $form->setContent($player . " has not made any transactions yet");
        }
        else {
            $form->setContent($playerBankMoney->get("Transactions"));
        }
        $form->addButton("§l§cEXIT\n§r§dClick to close...",0,"textures/ui/cancel");
        $form->sendtoPlayer($sender);
        return $form;
    }

    public static function getInstance(): BankUI {
        return self::$instance;
    }

}
