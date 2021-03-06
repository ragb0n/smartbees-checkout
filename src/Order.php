<?php

require_once('Database.php');
require_once('Cart.php');
require_once('Customer.php');


class Order{
    private $delivery; // 1 - Dostawa InPost, 2 - Dostawa DPD, 3 - Dostawa DPD pobranie
    private $payment; // 1 - Płatnośc PayU, 2 - Pobranie, 3 - przelew tradycyjny
    private Cart $cart; //obiekt koszyka, na potrzeby zadania tworzony jest podczas uruchomienia strony
    private Customer $customer; //obiekt klienta skłądającego zamówienie
    private $totalPrice = 0.00; //całkowita wartośc zamówienia (koszyk + dostawa  - ewentualny rabay)
    private $comment;
    private $addToNewsletter = false;
    private $deliveryAddress = [];

    public function __construct(array $newOrder, Cart $cart){
        $this->cart = $cart;
        $this->customer = new Customer($newOrder);
        $this->totalPrice = $cart->value;
        $this->comment = $newOrder['comment'];

        if($newOrder['delivery'] == 'inpost'){
            $this->delivery = 1;
            $this->totalPrice += 10.99;
        }
        if($newOrder['delivery'] == 'dpd'){
            $this->delivery = 2;
            $this->totalPrice += 18.00;
        }
        if($newOrder['delivery'] == 'dpdpob'){
            $this->delivery = 3;
            $this->totalPrice += 22.00;
        }

        if($newOrder['payment'] == 'payu'){
            $this->payment = 1;
        }
        if($newOrder['payment'] == 'pobranie'){
            $this->payment = 2;
        }
        if($newOrder['payment'] == 'przelew'){
            $this->payment = 3;
        }

        if($newOrder['newsletter'] == 'true'){
            $this->addToNewsletter = true;
        }

        if(isset($newOrder['diffAddress']) && isset($newOrder['diffPostalCode']) && isset($newOrder['diffCity'])){
            $this->deliveryAddress['address'] = $newOrder['diffAddress'];
            $this->deliveryAddress['postalCode'] = $newOrder['diffPostalCode'];
            $this->deliveryAddress['city'] = $newOrder['diffCity'];
        }else{
            $this->deliveryAddress['address'] = $newOrder['address'];
            $this->deliveryAddress['postalCode'] = $newOrder['postalCode'];
            $this->deliveryAddress['city'] = $newOrder['city'];
        }
    }

    //dodawanie zamówienia do bazy, na co składa się kolejne wykonanie funkcji dodających dane do odpowiednich tabel
    public function placeOrder(): int{
        try{
            $customerId = $this->customer->addCustomer();
            if($this->addToNewsletter){
                $this->signUpToNewsletter($customerId);
            }
            $deliveryAddressId = $this->addDeliveryAddress();
            $orderId = $this->addOrder($customerId, $deliveryAddressId);
            $cart = $this->cart->items;
            foreach($cart as $item){
                $this->addOrderDetail($orderId, $item);
            }
            return $orderId;
        }catch(Throwable $e){
            echo $e;
        }
    }

    //dodanie danych o zamówieniu do tabeli "orders"
    private function addOrder(int $customerId, int $deliveryId): int{
        try{
            $totalValue = $this->totalPrice;
            $delivery = $this->delivery;
            $payment = $this->payment;
            $comment = $this->comment;
            $query = "INSERT INTO orders(client_id, order_value, delivery, payment, comment, delivery_address) VALUES (
                '$customerId', 
                '$totalValue', 
                '$delivery', 
                '$payment',
                '$comment',
                '$deliveryId'
                );";
            $this->conn = new Database();
            $this->conn->connection->query($query);
            $id = $this->conn->connection->lastInsertId();
            return intval($id);
        }catch(Throwable $e){
            echo $e;
        }
    }

    //dodanie do bazy informacji o adresie dostawy zakupionych towarów
    private function addDeliveryAddress(): int{
        try{
            $address = $this->deliveryAddress['address'];
            $postalCode = $this->deliveryAddress['postalCode'];
            $city = $this->deliveryAddress['city'];
            $query = "INSERT INTO delivery_addresses(address, postal_code, city) VALUES (
                '$address', 
                '$postalCode', 
                '$city'
                );";
            $this->conn = new Database();
            $this->conn->connection->query($query);
            $deliveryAddressId = intval($this->conn->connection->lastInsertId());
            return $deliveryAddressId;
        }catch(Throwable $e){
            echo $e;
        }
    }

    //dodanie danych o szczegółach zamówienia do tabeli "order_details"
    private function addOrderDetail(int $orderId, Item $item): void{
        try{
            $productId = $item->id;
            $quantity = $item->quantity;
            $price = ($item->price) * $quantity;
            $query = "INSERT INTO order_details(order_nr, product_id, quantity, price) VALUES (
                '$orderId', 
                '$productId', 
                '$quantity', 
                '$price'
                );";
            $this->conn = new Database();
            $this->conn->connection->query($query);
        }catch(Throwable $e){
            echo $e;
        }
    }

    //zapisanie użytkownika do newslettera, jeśli wyraził taką chęć
    private function signUpToNewsletter(int $customerId): void{
        try{
            $query = "INSERT INTO newsletter_list(user_id) VALUES ($customerId);";
            $this->conn = new Database();
            $this->conn->connection->query($query);
        }catch(Throwable $e){
            echo $e;
        }
    }
}