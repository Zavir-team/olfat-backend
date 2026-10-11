<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Semantic data-integrity hardening.
 *
 * DBA reference: Mullins, "Database Administration" 2nd ed., Ch. 13 (Data Integrity:
 * check constraints, referential integrity) and Ch. 4 (Designing Indexes).
 *
 *  - Domain / range CHECK constraints are enforced by the DBMS itself, not only by
 *    application validation (MySQL 8.0.16+, MariaDB 10.2+, PostgreSQL). SQLite cannot
 *    add constraints to existing tables, so they are skipped there; the application
 *    layer still validates every value.
 *  - stock_movements is the audit trail of inventory; deleting a user must no longer
 *    cascade-delete that history (RESTRICT instead of CASCADE).
 *  - Supporting indexes for the most common report filters.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> table => [constraint name => expression] */
    private array $checks = [
        'users' => [
            'chk_users_role' => "role IN ('admin', 'staff')",
        ],
        'products' => [
            'chk_products_module' => "module IN ('tea', 'rice', 'detergent', 'raw_material', 'factory_material')",
        ],
        'sales' => [
            'chk_sales_amounts' => 'total_amount >= 0 AND paid_amount >= 0 AND remaining_amount >= 0',
            'chk_sales_payment_type' => "payment_type IN ('cash', 'credit', 'mixed')",
        ],
        'sale_items' => [
            'chk_sale_items_values' => 'quantity > 0 AND unit_price >= 0 AND line_total >= 0',
        ],
        'purchases' => [
            'chk_purchases_amounts' => 'total_amount >= 0 AND paid_amount >= 0 AND remaining_amount >= 0',
        ],
        'purchase_items' => [
            'chk_purchase_items_values' => 'quantity > 0 AND unit_price >= 0 AND line_total >= 0',
        ],
        'customer_payments' => [
            'chk_customer_payments_amount' => 'amount > 0',
        ],
        'supplier_payments' => [
            'chk_supplier_payments_amount' => 'amount > 0',
        ],
        'worker_payments' => [
            'chk_worker_payments_amount' => 'amount > 0',
        ],
        'external_debts' => [
            'chk_external_debts_direction' => "direction IN ('business_owes', 'person_owes')",
            'chk_external_debts_status' => "status IN ('open', 'closed')",
            'chk_external_debts_amounts' => 'original_amount >= 0 AND paid_amount >= 0 AND remaining_amount >= 0',
        ],
        'external_debt_payments' => [
            'chk_external_debt_payments_amount' => 'amount > 0',
        ],
        'home_expenses' => [
            'chk_home_expenses_amount' => 'amount >= 0',
        ],
        'damaged_stocks' => [
            'chk_damaged_stocks_quantity' => 'quantity > 0',
        ],
        'stock_movements' => [
            'chk_stock_movements_change' => 'quantity_change <> 0',
            'chk_stock_movements_type' => "type IN ('purchase', 'sale', 'sale_return', 'damage', 'adjustment', 'transfer_in', 'transfer_out')",
        ],
    ];

    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();

            $table->index(['location_id', 'occurred_at']);
        });

        Schema::table('customer_payments', function (Blueprint $table) {
            $table->index(['location_id', 'paid_at']);
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->index(['location_id', 'paid_at']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->index('status');
        });

        if (! $this->supportsCheckConstraints()) {
            return;
        }

        foreach ($this->checks as $tableName => $constraints) {
            foreach ($constraints as $name => $expression) {
                DB::statement("ALTER TABLE {$tableName} ADD CONSTRAINT {$name} CHECK ({$expression})");
            }
        }
    }

    public function down(): void
    {
        if ($this->supportsCheckConstraints()) {
            $dropKeyword = DB::connection()->getDriverName() === 'pgsql' ? 'CONSTRAINT' : 'CHECK';

            foreach ($this->checks as $tableName => $constraints) {
                foreach (array_keys($constraints) as $name) {
                    DB::statement("ALTER TABLE {$tableName} DROP {$dropKeyword} {$name}");
                }
            }
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropIndex(['location_id', 'paid_at']);
        });

        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropIndex(['location_id', 'paid_at']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['location_id', 'occurred_at']);

            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    private function supportsCheckConstraints(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true);
    }
};
