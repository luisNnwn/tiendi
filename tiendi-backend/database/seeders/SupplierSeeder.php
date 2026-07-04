<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'proveedor@tiendi.com'],
            [
                'name' => 'Proveedor Demo',
                'password' => 'password',
            ]
        );

        $supplier = Supplier::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'name' => 'Distribuidora Tiendi',
                'phone_number' => '5215510000001',
                'active' => true,
            ]
        );

        $products = [
            ['name' => 'Coca Cola', 'category' => 'Bebidas', 'unit' => 'caja', 'price' => 250.00],
            ['name' => 'Agua', 'category' => 'Bebidas', 'unit' => 'caja', 'price' => 120.00],
            ['name' => 'Galletas', 'category' => 'Snacks', 'unit' => 'paquete', 'price' => 45.00],
            ['name' => 'Papas', 'category' => 'Snacks', 'unit' => 'paquete', 'price' => 35.00],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                [
                    'supplier_id' => $supplier->id,
                    'name' => $product['name'],
                ],
                [
                    'category' => $product['category'],
                    'unit' => $product['unit'],
                    'price' => $product['price'],
                    'active' => true,
                ]
            );
        }
    }
}
