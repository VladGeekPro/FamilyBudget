<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\ExpenseChangeRequest;
use App\Models\ExpenseChangeRequestVote;
use App\Models\Overpayment;
use App\Models\PaidDebts;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $demoUser = User::factory()->create([
            'name' => 'Тестовый Пользователь',
            'email' => 'demo@example.com',
            'password' => bcrypt('password'),
        ]);

        $categoriesData = [
            [
                'name' => 'Продукты',
                'slug' => 'produkty',
                'notes' => 'Ежедневные продукты и бытовые покупки.',
                'suppliers' => ['Linella', 'Kaufland', 'Green Hills', 'Nr1', 'Family Market', 'Local Market'],
            ],
            [
                'name' => 'Кафе и рестораны',
                'slug' => 'kafe-restorany',
                'notes' => 'Питание вне дома.',
                'suppliers' => ['Andys Pizza', 'La Placinte', 'McDonalds', 'KFC', 'CoffeeVarka', 'Sensi Bar'],
            ],
            [
                'name' => 'Транспорт',
                'slug' => 'transport',
                'notes' => 'Такси, топливо, общественный транспорт.',
                'suppliers' => ['Yandex Go', 'iTaxi', 'Rompetrol', 'Lukoil', 'Moldcell Parking', 'Autogara'],
            ],
            [
                'name' => 'Коммунальные услуги',
                'slug' => 'kommunalnye-uslugi',
                'notes' => 'Свет, вода, газ, интернет.',
                'suppliers' => ['Premier Energy', 'Apa Canal', 'Moldovagaz', 'Orange Home', 'Moldtelecom'],
            ],
            [
                'name' => 'Здоровье',
                'slug' => 'zdorove',
                'notes' => 'Аптеки, анализы, приёмы врачей.',
                'suppliers' => ['Farmacia Felicia', 'Farmacia Familiei', 'Synevo', 'Medpark', 'Repromed'],
            ],
            [
                'name' => 'Дом и ремонт',
                'slug' => 'dom-remont',
                'notes' => 'Покупки для дома, мебель, ремонт.',
                'suppliers' => ['Supraten', 'Bomba', 'Enter', 'Maximum', 'Jysk', 'Casa Curata'],
            ],
            [
                'name' => 'Одежда и обувь',
                'slug' => 'odezhda-obuv',
                'notes' => 'Одежда, обувь, аксессуары.',
                'suppliers' => ['Zara', 'LC Waikiki', 'New Yorker', 'Sportlandia', 'Deichmann', 'Mango'],
            ],
            [
                'name' => 'Подписки и сервисы',
                'slug' => 'podpiski-servisy',
                'notes' => 'Онлайн-сервисы и подписки.',
                'suppliers' => ['Netflix', 'YouTube Premium', 'Spotify', 'Google One', 'iCloud', 'ChatGPT'],
            ],
            [
                'name' => 'Дети и образование',
                'slug' => 'deti-obrazovanie',
                'notes' => 'Сад, школа, кружки, учебные материалы.',
                'suppliers' => ['Bimboland', 'Librarius', 'Artico', 'SmartyKids', 'Kangaroo Club'],
            ],
            [
                'name' => 'Развлечения и отдых',
                'slug' => 'razvlecheniya-otdyh',
                'notes' => 'Кино, поездки, досуг.',
                'suppliers' => ['Patria Cinema', 'Aqua Magic', 'Booking', 'Airbnb', 'Wizz Air', 'Railway MD'],
            ],
        ];

        $categories = collect();
        $suppliersByCategory = [];

        foreach ($categoriesData as $categoryData) {
            $category = Category::create([
                'name' => $categoryData['name'],
                'slug' => $categoryData['slug'],
                'image' => null,
                'notes' => $categoryData['notes'],
            ]);

            $categories->push($category);
            $suppliersByCategory[$category->id] = collect();

            foreach ($categoryData['suppliers'] as $supplierName) {
                $supplier = Supplier::create([
                    'name' => $supplierName,
                    'slug' => Str::slug($supplierName . '-' . $categoryData['slug']),
                    'image' => null,
                    'category_id' => $category->id,
                ]);

                $suppliersByCategory[$category->id]->push($supplier);
            }
        }

        $users = collect([$admin, $demoUser]);
        $sumRanges = [
            'Продукты' => [120, 1200],
            'Кафе и рестораны' => [80, 900],
            'Транспорт' => [40, 700],
            'Коммунальные услуги' => [200, 1800],
            'Здоровье' => [100, 1600],
            'Дом и ремонт' => [150, 3500],
            'Одежда и обувь' => [150, 2500],
            'Подписки и сервисы' => [60, 700],
            'Дети и образование' => [100, 2200],
            'Развлечения и отдых' => [90, 2400],
        ];

        $notesByCategory = [
            'Продукты' => ['Покупка на неделю', 'Овощи и фрукты', 'Молочные продукты', 'Покупка для дома'],
            'Кафе и рестораны' => ['Обед с коллегами', 'Семейный ужин', 'Кофе и десерт', 'Доставка еды'],
            'Транспорт' => ['Поездка на работу', 'Такси домой', 'Заправка авто', 'Парковка в центре'],
            'Коммунальные услуги' => ['Оплата электроэнергии', 'Оплата воды', 'Оплата газа', 'Интернет и связь'],
            'Здоровье' => ['Покупка лекарств', 'Консультация врача', 'Анализы', 'Витамины'],
            'Дом и ремонт' => ['Хозтовары', 'Мелкий ремонт', 'Покупка для кухни', 'Товары для уборки'],
            'Одежда и обувь' => ['Покупка одежды', 'Новая обувь', 'Аксессуары', 'Спортивная форма'],
            'Подписки и сервисы' => ['Ежемесячная подписка', 'Оплата облака', 'Музыкальный сервис', 'Видеосервис'],
            'Дети и образование' => ['Кружок', 'Канцтовары', 'Книги', 'Развивающие занятия'],
            'Развлечения и отдых' => ['Кино', 'Семейный отдых', 'Билеты', 'Поездка выходного дня'],
        ];

        $startDate = Carbon::now()->subMonths(120)->startOfDay();
        $endDate = Carbon::now()->startOfDay();

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDays(1)) {
            $entriesCount = random_int(3, 4);

            for ($i = 0; $i < $entriesCount; $i++) {
                $category = $categories->random();
                $supplier = $suppliersByCategory[$category->id]->random();
                [$minSum, $maxSum] = $sumRanges[$category->name];
                $note = $notesByCategory[$category->name][array_rand($notesByCategory[$category->name])];

                Expense::create([
                    'user_id' => $users->random()->id,
                    'date' => $date->toDateString(),
                    'category_id' => $category->id,
                    'supplier_id' => $supplier->id,
                    'sum' => random_int($minSum, $maxSum),
                    'notes' => $note,
                ]);
            }
        }

        Overpayment::factory(20)->create();

        Debt::factory(12)->create();

        $changeRequests = ExpenseChangeRequest::factory(100)->create();

        foreach ($changeRequests as $changeRequest) {
            $randomUser = User::inRandomOrder()->first();
            ExpenseChangeRequestVote::factory()->create([
                'expense_change_request_id' => $changeRequest->id,
                'user_id' => $randomUser->id,
            ]);
        }

        PaidDebts::factory(10)->create();
    }
}
