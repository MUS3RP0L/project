<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */

    public function run()
    {
        Model::unguard();
        $this->call(WorkflowTableSeeder::class);
        $this->call(ObservationTypeTableSeeder::class);
        $this->call(CityTableSeeder::class);
        $this->call(UserTableSeeder::class);
        $this->call(DegreeTableSeeder::class);
        $this->call(StateTableSeeder::class);
        $this->call(IpcRateTableSeeder::class);
        $this->call(ContributionRateTableSeeder::class);
        $this->call(CategoryTableSeeder::class);
        $this->call(BreakdownTableSeeder::class);
        $this->call(UnitTableSeeder::class);
        $this->call(VoucherTypeTableSeeder::class);
        $this->call(EconomicComplementModalityTableSeeder::class);
        $this->call(PensionEntityTableSeeder::class);
        $this->call(ComplementaryFactorTableSeeder::class);
        $this->call(EconomicComplementRequirementTableSeeder::class);        

        Model::reguard();
    }
}
