<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('login', 'Auth\AuthController@getLogin');
Route::post('login', 'Auth\AuthController@postLogin');
Route::get('logout', 'Auth\AuthController@getLogout');

Route::group(['middleware' => 'auth'], function() {

	// Dashboard
	Route::get('dashboard', ['as' => 'dashboard', 'uses' => 'Dashboard\DashboardController@showIndex']);
	Route::get('/', ['as' => 'dashboard', 'uses' => 'Dashboard\DashboardController@showIndex']);
	Route::get('home', ['as' => 'dashboard', 'uses' => 'Dashboard\DashboardController@showIndex']);
	Route::get('search', 'Dashboard\DashboardController@searchAffiliate');

	// User Management
	Route::resource('user', 'User\UserController');
	Route::get('user/block/{user}', array('as'=>'user/block', 'uses'=>'User\UserController@Block'));
	Route::get('user/unblock/{user}', array('as'=>'user/unblock', 'uses'=>'User\UserController@Unblock'));
	Route::get('get_user', array('as'=>'get_user', 'uses'=>'User\UserController@Data'));
	Route::get('get_role', array('as'=>'get_role', 'uses'=>'User\UserController@getRole'));

	// Contribution Rate
	Route::resource('contribution_rate', 'Rate\ContributionRateController');
	Route::get('get_contribution_rate', array('as'=>'get_contribution_rate', 'uses'=>'Rate\ContributionRateController@Data'));

	// IPC Rate
	Route::resource('ipc_rate', 'Rate\IpcRateController');
	Route::get('get_ipc_rate', array('as'=>'get_ipc_rate', 'uses'=>'Rate\IpcRateController@Data'));

	// Base Wage
	Route::resource('base_wage', 'Wage\BaseWageController');
	Route::get('get_first_level_base_wage', array('as'=>'get_first_level_base_wage', 'uses'=>'Wage\BaseWageController@FirstLevelData'));
	Route::get('get_second_level_base_wage', array('as'=>'get_second_level_base_wage', 'uses'=>'Wage\BaseWageController@SecondLevelData'));
	Route::get('get_third_level_base_wage', array('as'=>'get_third_level_base_wage', 'uses'=>'Wage\BaseWageController@ThirdLevelData'));
	Route::get('get_fourth_level_base_wage', array('as'=>'get_fourth_level_base_wage', 'uses'=>'Wage\BaseWageController@FourthLevelData'));

	// Monthly Report
	Route::get('monthly_report', array('as'=>'monthly_report', 'uses'=>'Report\ReportController@ShowMonthlyReport'));
	Route::post('monthly_report', array('as'=>'monthly_report', 'uses'=> 'Report\ReportController@GenerateMonthlyReport'));

	// Affiliate
	Route::resource('affiliate', 'Affiliate\AffiliateController');
	Route::get('print_affiliate/{affiliate_id}', 'Affiliate\AffiliateController@print_affiliate');
	Route::post('search_affiliate', array('as'=>'search_affiliate', 'uses'=>'Affiliate\AffiliateController@SearchAffiliate'));
	Route::get('get_affiliate', array('as'=>'get_affiliate', 'uses'=>'Affiliate\AffiliateController@Data'));
	Route::post('updatePolice/{affiliate_id}',array('as'=>'updatePolice','uses'=>'Affiliate\AffiliateController@updatePolice'));
	// Affiliate Address
	Route::resource('affiliate_address', 'Affiliate\AffiliateAddressController');
	// Record Affiliate
	Route::get('get_record/{affiliate_id}', array('as'=>'get_record', 'uses'=>'Affiliate\AffiliateRecordController@Data'));
	// Spouses
	Route::resource('spouse', 'Affiliate\SpouseController');

	// Contribution
	Route::resource('contribution', 'Contribution\ContributionController');
	Route::get('show_contributions/{affiliate_id}', array('as'=>'show_contributions', 'uses'=>'Contribution\ContributionController@ShowContributions'));
	Route::get('get_contribution/{affiliate_id}', array('as'=>'get_contribution', 'uses'=>'Contribution\ContributionController@ShowData'));
	Route::get('select_contribution/{affiliate_id}', array('as'=>'select_contribution', 'uses'=>'Contribution\ContributionController@SelectContribution'));
	Route::get('get_select_contribution/{id}', array('as'=>'get_select_contribution', 'uses'=>'Contribution\ContributionController@SelectData'));

	// Direct Contribution
	Route::resource('direct_contribution', 'Contribution\DirectContributionController');
	Route::get('calculation_contribution/{affiliate_id}/{year}/{type}', 'Contribution\DirectContributionController@CalculationContribution');
	Route::post('calculation_contribution', 'Contribution\DirectContributionController@GenerateCalculationContribution');
	Route::get('get_direct_contribution', array('as'=>'get_direct_contribution', 'uses'=>'Contribution\DirectContributionController@Data'));
	Route::get('print_direct_contribution/{direct_contribution_id}', 'Contribution\DirectContributionController@PrintDirectContribution');

	// Vouchers
	Route::resource('voucher', 'Voucher\VoucherController');
	Route::get('get_voucher', array('as'=>'get_voucher', 'uses'=>'Voucher\VoucherController@Data'));
	Route::get('print_voucher/{voucher_id}', 'Voucher\VoucherController@PrintVoucher');
	Route::get('print_compromise/{afid}', 'Contribution\DirectContributionController@PrintCompromise');

	

	// Complementary Factor
	Route::resource('complementary_factor', 'EconomicComplement\ComplementaryFactorController');
	Route::get('get_complementary_factor_old_age', array('as'=>'get_complementary_factor_old_age', 'uses'=>'EconomicComplement\ComplementaryFactorController@old_ageData'));
	Route::get('get_complementary_factor_widowhood', array('as'=>'get_complementary_factor_widowhood', 'uses'=>'EconomicComplement\ComplementaryFactorController@widowhoodData'));

	// Economic Complement Procedure
	Route::resource('economic_complement', 'EconomicComplement\EconomicComplementController');
	Route::get('economic_complement_reception_first_step/{affiliate_id}','EconomicComplement\EconomicComplementController@ReceptionFirstStep');
	Route::get('economic_complement_reception_second_step/{economic_complement_id}','EconomicComplement\EconomicComplementController@ReceptionSecondStep');
	Route::get('economic_complement_reception_third_step/{economic_complement_id}','EconomicComplement\EconomicComplementController@ReceptionThirdStep');
	Route::get('get_economic_complement', array('as'=>'get_economic_complement', 'uses'=>'EconomicComplement\EconomicComplementController@Data'));
	Route::get('get_economic_complement_by_affiliate', array('as'=>'get_economic_complement_by_affiliate', 'uses'=>'EconomicComplement\EconomicComplementController@Data_by_affiliate'));
	Route::get('get_economic_complement_type/{id}', array('as'=>'get_economic_complement_type', 'uses'=>'EconomicComplement\EconomicComplementController@getEconomicComplementType'));

	Route::get('averages', array('as' => 'averages', 'uses' => 'EconomicComplement\EconomicComplementReportController@average'));
	Route::get('get_average', array('as'=>'get_average','uses' =>'EconomicComplement\EconomicComplementReportController@Data'));
	Route::get('print_average', array('as'=>'print_average','uses' =>'EconomicComplement\EconomicComplementReportController@print_average'));
	Route::get('get_updated_list', array('as'=>'updated_list','uses' =>'EconomicComplement\EconomicComplementReportController@updated_list'));
	Route::post('export_updated_list', array('as'=>'export_updated_list','uses' =>'EconomicComplement\EconomicComplementReportController@export_updated_list'));

	Route::get('print_sworn_declaration/{economic_complement_id}/{type}', 'EconomicComplement\EconomicComplementController@print_sworn_declaration');
	Route::get('print_eco_com_reports/{economic_complement_id}/{type}', 'EconomicComplement\EconomicComplementController@print_eco_com_reports');

	// Economic Complement Report
	Route::resource('report_complement', 'EconomicComplement\EconomicComplementReportController');
	Route::post('report_generator', array('as'=>'report_generator', 'uses'=> 'EconomicComplement\EconomicComplementReportController@report_generator'));
	Route::resource('importexport', 'EconomicComplement\EconomicComplementImportExportController');
	Route::post('import_senasir', array('as'=>'import_senasir', 'uses'=> 'EconomicComplement\EconomicComplementImportExportController@import_from_senasir'));
	Route::post('import_aps', array('as'=>'import_aps', 'uses'=> 'EconomicComplement\EconomicComplementImportExportController@import_from_aps'));
	Route::post('import_bank', array('as'=>'import_bank', 'uses'=> 'EconomicComplement\EconomicComplementImportExportController@import_from_bank'));
	Route::post('export_aps', array('as'=>'export_aps', 'uses'=> 'EconomicComplement\EconomicComplementImportExportController@export_to_aps'));
	Route::post('export_bank', array('as'=>'export_bank', 'uses'=> 'EconomicComplement\EconomicComplementImportExportController@export_to_bank'));

	Route::get('get_causes_by_state', array('as'=>'get_causes_by_state', 'uses'=>'EconomicComplement\EconomicComplementController@getCausesByState'));

	// Economic Complement Record
	Route::get('get_economic_complement_record/{ecomomic_complement_id}', array('as'=>'get_economic_complement_record', 'uses'=>'EconomicComplement\EconomicComplementController@get_record'));

	//Routes for Observations
	Route::resource('observation','Observation\AffiliateObservationController');
	Route::get('get_observations',['as'=>'get_observations','uses'=>'Observation\AffiliateObservationController@showOfAffiliate']);
	Route::get('observation/deleteOb/{id}',['as'=>'deleteOb','uses'=>'Observation\AffiliateObservationController@deleteOb']);
	Route::post('observation/update',['as'=>'updateObservation','uses'=>'Observation\AffiliateObservationController@update']);

	//Routes for inbox
	Route::resource('inbox', 'Inbox\InboxController');
	Route::get('received_data', array('as'=>'received_data','uses'=>'Inbox\InboxController@DataReceived'));
	Route::get('edited_data', array('as'=>'edited_data','uses'=>'Inbox\InboxController@DataEdited'));

	//Observations
	Route::get('print_observations/{id_complement}/{type}', 'Affiliate\AffiliateController@print_observations');
	//complaints
	Route::get('print_correct/{id_complement}', 'Affiliate\AffiliateController@print_correct_grading');

	//procedures
	Route::resource('economic_complement_procedure', 'EconomicComplement\EconomicComplementProcedureController');
	Route::POST('eco_com_pro_delete',['as'=>'eco_com_pro_delete','uses'=>'EconomicComplement\EconomicComplementProcedureController@deleteProcedure']);
	Route::get('eco_com_pro_data',array('as'=>'eco_com_pro_data','uses'=> 'EconomicComplement\EconomicComplementProcedureController@Data'));
});

define('ACCESS', env('ACCESS_PASS'));