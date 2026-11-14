<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\ExchangeRate;
use App\Models\ExpenseTemplate;
use App\Models\FinancialPeriod;
use App\Models\Occupancy;
use App\Models\PeriodItem;
use App\Models\Person;
use App\Models\Property;
use App\Models\Setting;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Los datos de demostración se crean únicamente en local o pruebas.');
        }
        if (Property::exists()) {
            $this->command?->warn('Ya existen inmuebles. Se conservaron los datos actuales.');

            return;
        }
        $password = Str::random(20);
        DB::transaction(function () use ($password): void {
            $finance = app(FinanceService::class);
            $start = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
            $paid = now()->subMonthNoOverflow()->startOfMonth()->addDays(5)->toDateString();
            $today = now()->toDateString();
            $admin = User::firstOrCreate(['email' => 'admin@condominio.test'], ['name' => 'Administradora de demostración', 'password' => $password, 'role' => 'admin', 'active' => true]);
            User::firstOrCreate(['email' => 'super@condominio.test'], ['name' => 'Superusuario de demostración', 'password' => $password, 'role' => 'superuser', 'active' => true]);
            Setting::updateOrCreate(['key' => 'demo'], ['value' => true]);
            Setting::updateOrCreate(['key' => 'bank_instructions'], ['value' => 'Datos de demostración. Registra la cuenta bancaria real antes de emitir documentos para uso operativo.']);
            $bank = BankAccount::create(['name' => 'Banco Caroní · Demostración', 'opening_balance_bs_cents' => 75000000, 'opening_date' => $start, 'active' => true]);
            $finance->setFundOpening('reserve', 50000, $start, $admin->id);
            $finance->setFundOpening('provision', 36000, $start, $admin->id);
            $properties = collect();
            for ($index = 1; $index <= 90; $index++) {
                $code = str_pad((string) $index, 3, '0', STR_PAD_LEFT);
                $property = Property::create(['code' => 'TH DEMO-'.$code, 'occupancy_status' => $index % 8 === 0 ? 'rented' : 'occupied', 'active' => true]);
                $properties->push($property);
                $person = Person::create(['name' => 'Propietario de prueba '.$code, 'identity_number' => 'DEMO-'.$code, 'email' => 'vecino'.$code.'@example.test', 'phone' => '']);
                Occupancy::create(['property_id' => $property->id, 'person_id' => $person->id, 'relationship' => 'owner', 'starts_on' => $start]);
                if ($index % 8 !== 0) {
                    Occupancy::create(['property_id' => $property->id, 'person_id' => $person->id, 'relationship' => 'resident', 'starts_on' => $start]);
                }
            }
            $finance->setOpeningBalance($properties[0], 3000, $start, $admin->id);
            $finance->setOpeningBalance($properties[1], -2000, $start, $admin->id);
            User::firstOrCreate(['email' => 'vecino@condominio.test'], ['name' => 'Vecino de demostración', 'password' => $password, 'role' => 'user', 'active' => true, 'permissions' => ['community.view', 'finance.view', 'payments.view', 'reports.view'], 'property_ids' => [$properties[0]->id]]);
            $previous = FinancialPeriod::create(['month' => substr($start, 0, 7), 'status' => 'draft', 'reserve_basis_points' => 1000]);
            PeriodItem::create(['financial_period_id' => $previous->id, 'description' => 'Servicios del período · ejemplo', 'category' => 'sector_fixed', 'amount_usd_cents' => 90000, 'is_provision' => false]);
            $finance->issuePeriod($previous, $admin->id);
            $finance->recordPayment(['bank_account_id' => $bank->id, 'paid_on' => $paid, 'amount_bs_cents' => 250000, 'rate_micros' => 100000000, 'reference' => 'DEMO-TRANSFERENCIA-001', 'idempotency_key' => (string) Str::uuid(), 'splits' => [['property_id' => $properties[0]->id, 'amount_usd_cents' => 2500]]], $admin->id);
            $finance->recordExpense(['bank_account_id' => $bank->id, 'paid_on' => $paid, 'amount_bs_cents' => 1200000, 'rate_micros' => 100000000, 'description' => 'Mantenimiento de áreas verdes · ejemplo', 'fund' => 'operating', 'idempotency_key' => (string) Str::uuid()], $admin->id);
            foreach ([$paid, $today] as $date) {
                ExchangeRate::create(['date' => $date, 'rate_micros' => 100000000, 'reference_date' => $date, 'source' => 'Demostración · tasa ficticia', 'fetched_at' => now()]);
            }
            $period = FinancialPeriod::create(['month' => now()->format('Y-m'), 'status' => 'draft', 'reserve_basis_points' => 1000]);
            $items = [
                ['sector_fixed', 'Mantenimiento áreas verdes y calles internas', 12000],
                ['sector_fixed', 'Gestión administrativa', 15000],
                ['sector_fixed', 'Comisiones bancarias', 4172],
                ['sector_fixed', 'Intereses bancarios', -235],
                ['sector_variable', 'Instalación de 4 lámparas Capri', 4000],
                ['sector_variable', 'Montar acrílicos y tapas en 3 postes', 1500],
                ['sector_variable', 'Provisión cabezales de luminarias · cuota 1/4', -18000, true],
                ['sector_variable', 'Provisión cabezales de luminarias · cuota 2/4', -18000, true],
                ['sector_variable', '2 cabezales de luminarias · cuota 1/4', 21152],
                ['sector_variable', '2 cabezales de luminarias · cuota 2/4', 21153],
                ['sector_variable', 'Flete de un cabezal', 938],
                ['sector_variable', 'Flete de tres cabezales', 2334],
                ['sector_variable', 'Brochas de pintura', 450],
                ['sector_variable', 'Materiales y pintura azul', 4400],
                ['sector_variable', 'Pintura blanca', 10914],
                ['common_fixed', 'Servicio de vigilancia', 57663],
                ['common_fixed', 'Mantenimiento entrada principal', 580],
                ['common_fixed', 'Mantenimiento piscina', 4644],
                ['common_fixed', 'Mantenimiento parque', 6540],
                ['common_fixed', 'Mantenimiento terreno y cancha', 7547],
                ['common_fixed', 'Servicio privado de aseo domiciliario', 16989],
                ['common_fixed', 'Hidrobolívar', 1296],
                ['common_fixed', 'Corpoelec', 329],
                ['common_fixed', 'Recarga telefónica portón de entrada', 140],
                ['common_fixed', 'Recarga telefónica portón de salida', 140],
                ['common_fixed', 'Recarga telefónica garita principal', 18],
                ['common_fixed', 'Recolección de maleza', 3096],
            ];
            foreach ($items as $item) {
                $template = ExpenseTemplate::create(['category' => $item[0], 'description' => $item[1], 'amount_usd_cents' => $item[2], 'is_provision' => $item[3] ?? false, 'recurring' => str_ends_with($item[0], '_fixed'), 'active' => true]);
                PeriodItem::create($template->only(['category', 'description', 'amount_usd_cents', 'is_provision']) + ['financial_period_id' => $period->id, 'expense_template_id' => $template->id, 'service_date' => $start]);
            }
        });
        $this->command?->info('Demostración creada. Cuentas: super@condominio.test, admin@condominio.test, vecino@condominio.test');
        $this->command?->info('Contraseña inicial de las cuentas nuevas: '.$password);
    }
}
