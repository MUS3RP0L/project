<?php
namespace Muserpol\Http\Controllers\EconomicComplement;

use Illuminate\Http\Request;

use Muserpol\Http\Requests;
use Muserpol\Http\Controllers\Controller;
use Illuminate\Contracts\Filesystem\Factory;
use Storage;
use File;
use Log;
use DB;
use Auth;
use Session;
use Carbon\Carbon;
use Muserpol\Helper\Util;
use Maatwebsite\Excel\Facades\Excel;

use Muserpol\Affiliate;
use Muserpol\AffiliateObservation;

use Muserpol\EconomicComplement;
use Muserpol\EconomicComplementProcedure;
use Muserpol\EconomicComplementApplicant;
use Muserpol\EconomicComplementLegalGuardian;
use Muserpol\EconomicComplementState;
use Muserpol\Devolution;
use stdClass;

use App\CustomCollection;

class EconomicComplementImportExportController extends Controller
{

	public function index()
	{

	}

	public static function import_from_senasir(Request $request)
	{
		if ($request->hasFile('archive')) {
			global $year, $semester, $results, $i, $afi, $list;
			$reader = $request->file('archive');
			$filename = $reader->getRealPath();
			$year = $request->year;
			$semester = $request->semester;
			Excel::load($filename, function ($reader) {
				global $results, $i, $afi, $list;
				ini_set('memory_limit', '-1');
				ini_set('max_execution_time', '-1');
				ini_set('max_input_time', '-1');
				set_time_limit('-1');
				$results = collect($reader->get());
			});

        //  return response()->json($results);
			$afi;
			$found = 0;
			$nofound = 0;
			$distinct = 0;
			$procedure = EconomicComplementProcedure::whereYear('year', '=', $year)->where('semester', '=', $semester)->first();
			foreach ($results as $datos) {

				$ext = ($datos->num_com ? "-" . $datos->num_com : '');
				$ext = str_replace(' ', '', $ext);
				$ci = trim(Util::removeSpaces(trim($datos->carnet)) . ((trim(Util::removeSpaces($datos->num_com)) != '') ? '-' . $datos->num_com : ''));
				if ($datos->renta == "DERECHOHABIENTE") {
					$comp = DB::table('eco_com_applicants') // VIUDEDAD
						->select(DB::raw('eco_com_applicants.identity_card as ci_app,economic_complements.*, eco_com_types.id as type'))
						->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
						->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
						->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
						->leftJoin('eco_com_types', 'eco_com_modalities.eco_com_type_id', '=', 'eco_com_types.id')
                  // ->whereRaw("LTRIM(eco_com_applicants.identity_card,'0') ='".rtrim($datos->carnet.''.$ext)."'")
						->whereRaw("ltrim(trim(eco_com_applicants.identity_card),'0') ='" . ltrim(trim($ci), '0') . "'")
						->where('eco_com_types.id', '=', 2)
						->where('affiliates.pension_entity_id', '=', 5)
						->where('economic_complements.eco_com_procedure_id', '=', $procedure->id)
						->first();
				} elseif ($datos->renta == "TITULAR") {
					$comp = DB::table('eco_com_applicants') // VEJEZ
						->select(DB::raw('eco_com_applicants.identity_card as ci_app,economic_complements.*, eco_com_types.id as type'))
						->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
						->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
						->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
						->leftJoin('eco_com_types', 'eco_com_modalities.eco_com_type_id', '=', 'eco_com_types.id')
						->whereRaw("ltrim(trim(eco_com_applicants.identity_card),'0') ='" . ltrim(trim($ci), '0') . "'")
                  // ->whereRaw("LTRIM(eco_com_applicants.identity_card,'0') ='".rtrim($datos->carnet.''.$ext)."'")                
						->where('eco_com_types.id', '=', 1)
						->where('affiliates.pension_entity_id', '=', 5)
						->where('economic_complements.eco_com_procedure_id', '=', $procedure->id)
						->first();
				} else {
					$comp = DB::table('eco_com_applicants') // ORFANDAD
						->select(DB::raw('eco_com_applicants.identity_card as ci_app,economic_complements.*, eco_com_types.id as type'))
						->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
						->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
						->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
						->leftJoin('eco_com_types', 'eco_com_modalities.eco_com_type_id', '=', 'eco_com_types.id')
						->whereRaw("ltrim(trim(eco_com_applicants.identity_card),'0') ='" . ltrim(trim($ci), '0') . "'")
                  // ->whereRaw("LTRIM(eco_com_applicants.identity_card,'0') ='".rtrim($datos->carnet.''.$ext)."'")                
						->where('eco_com_types.id', '=', 3)
						->where('affiliates.pension_entity_id', '=', 5)
						->where('economic_complements.eco_com_procedure_id', '=', $procedure->id)
						->first();
				}
				$procedure = EconomicComplementProcedure::whereYear('year', '=', $year)->where('semester', '=', $semester)->first();
				if ($comp && $procedure->indicator > 0) {

					$ecomplement = EconomicComplement::where('id', '=', $comp->id)->first();
					if ((is_null($ecomplement->total_rent) || $ecomplement->total_rent == 0) && $procedure->indicator > 0) {
						$reimbursements = $datos->reintegro_importe_adicional + $datos->reintegro_inc_gestion;
						$discount = $datos->renta_dignidad + $datos->reintegro_renta_dignidad + $datos->reintegro_importe_adicional + $datos->reintegro_inc_gestion;
						$total_rent = $datos->total_ganado - $discount;

						if ($comp->type == 1 && $total_rent < $procedure->indicator)  //Vejez Senasir
						{
							$ecomplement->eco_com_modality_id = 8;
						} elseif ($comp->type == 2 && $total_rent < $procedure->indicator) //Viudedad 
						{
							$ecomplement->eco_com_modality_id = 9;
						} elseif ($comp->type == 3 && $total_rent < $procedure->indicator) //Orfandad 
						{
							$ecomplement->eco_com_modality_id = 12;
						}
						$ecomplement->sub_total_rent = $datos->total_ganado;
						$ecomplement->total_rent = $total_rent;
						$ecomplement->dignity_pension = $datos->renta_dignidad;
						$ecomplement->reimbursement = $reimbursements;
						$ecomplement->rent_type = 'Automatico';
						$ecomplement->save();
						$found++;
					} 
					// else {
					// 	$reimbursements = $datos->reintegro_importe_adicional + $datos->reintegro_inc_gestion;
					// 	$discount = $datos->renta_dignidad + $datos->reintegro_renta_dignidad + $datos->reintegro_importe_adicional + $datos->reintegro_inc_gestion;
					// 	$total_rent = $datos->total_ganado - $discount;

					// 	if (abs(($ecomplement->total_rent - $total_rent) / $total_rent) < 0.00001) {
					// 	} else {
					// 		$distinct++;
					// 		Log::info($ecomplement->id . ' ' . $ecomplement->affiliate->identity_card . "total rent ECO:" . $ecomplement->total_rent . "TOTAL RENT SEN:" . $total_rent);
					// 	}
					// }

				} else {
					$nofound++;
					$i++;
					$list = $comp;
				}
			}

			Session::flash('message', "Importación Exitosa" . " F:" . $found . " NF:" . $nofound . ' RENTAS DISTINTAS: ' . $distinct);
			return redirect('economic_complement');
		}
		return back();
	}

	public static function import_from_aps(Request $request)
	{

		if ($request->hasFile('archive')) {
			global $year, $semester, $results, $i, $afi, $list;
			$reader = $request->file('archive');
			$filename = $reader->getRealPath();
			$year = $request->year;
			$semester = $request->semester;
			Excel::load($filename, function ($reader) {
				global $results, $i, $afi, $list;
				ini_set('memory_limit', '-1');
				ini_set('max_execution_time', '-1');
				ini_set('max_input_time', '-1');
				set_time_limit('-1');
				$results = collect($reader->get());
			});

			$afi;
			$found = 0;
			$nofound = 0;
			$procedure = EconomicComplementProcedure::whereYear('year', '=', $year)->where('semester', '=', $semester)->first();
			foreach ($results as $datos) {
				$nua = ltrim((string)$datos->nrosip_titular, "0");
				$ci = explode("-", ltrim($datos->nro_identificacion, "0"));
				$ci1 = $ci[0];
				$afi = DB::table('economic_complements')
					->select(DB::raw('affiliates.identity_card as ci_afi,economic_complements.*, eco_com_types.id as type'))
					->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
					->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
					->leftJoin('eco_com_types', 'eco_com_modalities.eco_com_type_id', '=', 'eco_com_types.id')
					->whereRaw("split_part(LTRIM(affiliates.identity_card,'0'), '-',1) = '" . $ci1 . "'")
					->whereRaw("LTRIM(affiliates.nua::text,'0') ='" . $nua . "'")
					->where('affiliates.pension_entity_id', '!=', 5)
					->whereYear('economic_complements.year', '=', $year)
					->where('economic_complements.semester', '=', $semester)->first();


				if ($afi) {
					$ecomplement = EconomicComplement::where('id', '=', $afi->id)->first();
					if ((is_null($ecomplement->total_rent) || $ecomplement->total_rent == 0) && $procedure->indicator > 0) {
						$comp1 = 0;
						$comp2 = 0;
						$comp3 = 0;
						if ($datos->total_cc > 0) {
							$comp1 = 1;
						}
						if ($datos->total_fsa > 0) {
							$comp2 = 1;
						}
						if ($datos->total_fs > 0) {
							$comp3 = 1;
						}
						$comp = $comp1 + $comp2 + $comp3;

                    //Vejez
						if ($afi->type == 1) {
							if ($comp == 1 && $datos->total_pension >= $procedure->indicator) {
								$ecomplement->eco_com_modality_id = 4;
							} elseif ($comp == 1 && $datos->total_pension < $procedure->indicator) {
								$ecomplement->eco_com_modality_id = 6;
							} elseif ($comp > 1 && $datos->total_pension < $procedure->indicator) {
								$ecomplement->eco_com_modality_id = 8;
							}
						}
                   //Viudedad
						elseif ($afi->type == 2) {
							if ($comp == 1 && $datos->total_pension >= $procedure->indicator) {
								$ecomplement->eco_com_modality_id = 5;
							} elseif ($comp == 1 && $datos->total_pension < $procedure->indicator) {
								$ecomplement->eco_com_modality_id = 7;
							} elseif ($comp > 1 && $datos->total_pension < $procedure->indicator) {
								$ecomplement->eco_com_modality_id = 9;
							}
						} else { //ORFANDAD
							if ($comp == 1 && $datos->total_pension >= $procedure->indicator) {
								$ecomplement->eco_com_modality_id = 10;
							} elseif ($comp == 1 && $datos->total_pension < $procedure->indicator) {
								$ecomplement->eco_com_modality_id = 11;
							} elseif ($comp > 1 && $datos->total_pension < $procedure->indicator) {
								$ecomplement->eco_com_modality_id = 12;
							}
						}
						$ecomplement->total_rent = $datos->total_pension;
						$ecomplement->aps_total_cc = $datos->total_cc;
						$ecomplement->aps_total_fsa = $datos->total_fsa;
						$ecomplement->aps_total_fs = $datos->total_fs;
						$ecomplement->rent_type = 'Automatico';
						$ecomplement->save();
						$found++;
						Log::info($ci);
					}
				} else {
					$nofound++;
					$i++;
					$list[] = $datos;
				}

			}

			Session::flash('message', "Importación Exitosa" . " F:" . $found . " NF:" . $nofound);
			return redirect('economic_complement');
		}
	}

	public static function import_from_bank(Request $request)
	{
		global $year, $semester, $result;
		if ($request->hasFile('archive')) {

			$reader = $request->file('archive');
			$filename = $reader->getRealPath();
			$year = $request->year;
			$semester = $request->semester;
			Excel::load($filename, function ($reader) {
				global $result;
				ini_set('memory_limit', '-1');
				ini_set('max_execution_time', '-1');
				ini_set('max_input_time', '-1');
				set_time_limit('-1');
				$result = collect($reader->get());
			});


			$found = 0;
			$nofound = 0;  
     // dd($result->toArray());
			foreach ($result as $valor) {  // dd($valor->descripcion2);
				$ecom = EconomicComplement::where('affiliate_id', '=', $valor->descripcion2)
					->whereYear('year', '=', $year)
					->where('semester', '=', $semester)->first();
				if ($ecom) {
					$ecom->eco_com_state_id = 1;
					$ecom->wf_current_state_id = 8;
					$ecom->payment_number = $valor->nro_comprobante;
					$ecom->payment_date = $valor->fecha_pago;
					$ecom->bank_agency = $valor->agencia . ' - ' . $valor->cod_agencia;
					$ecom->save();
					$found++;
				} else {
					$nofound++;
				}
			}
			Session::flash('message', "Importación Exitosa" . " " . $found);
			return redirect('economic_complement');
		}
	}

    //############################################## EXPORT AFFILIATES TO APS ###################################
	public function export_to_aps()
	{
		global $year, $semester, $i, $afi;
      // $year = $request->year;
      // $semester = $request->semester;
		Excel::create('Muserpol_para_aps', function ($excel) {
			global $year, $semester, $j;
			$j = 2;
			$excel->sheet("AFILIADOS_PARA_APS_" . $year, function ($sheet) {
				global $year, $semester, $j, $i;
				$i = 1;
				$sheet->row(1, array('NRO', 'TIPO_ID', 'NUM_ID', 'EXTENSION', 'CUA', 'PRIMER_APELLIDO_T', 'SEGUNDO_APELLIDO_T', 'PRIMER_NOMBRE_T', 'SEGUNDO_NOMBRE_T', 'APELLIDO_CASADA_T', 'FECHA_NACIMIENTO_T'));
				$afi = DB::table('eco_com_applicants')
					->select(DB::raw('distinct on (affiliates.identity_card) affiliates.identity_card,economic_complements.id,economic_complements.affiliate_id,cities.third_shortened,affiliates.nua,affiliates.last_name,affiliates.mothers_last_name,affiliates.first_name,affiliates.second_name,affiliates.surname_husband,affiliates.birth_date'))
					->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
					->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
					->leftJoin('cities', 'affiliates.city_identity_card_id', '=', 'cities.id')
					->where('affiliates.pension_entity_id', '<>', 5)
                     //->where('economic_complements.sub_total_rent','>', 0)
                     //->whereNull('economic_complements.total_rent')
                    //  ->whereYear('economic_complements.year', '=', $year)
                    //  ->where('economic_complements.semester', '=', $semester)
					->get();
				foreach ($afi as $datos) {
					$sheet->row($j, array($i, "I", Util::addcero($datos->identity_card, 13), $datos->third_shortened, Util::addcero($datos->nua, 9), $datos->last_name, $datos->mothers_last_name, $datos->first_name, $datos->second_name, $datos->surname_husband, Util::DateUnion($datos->birth_date)));
					$j++;
					$i++;
				}
			});
		})->export('xlsx');
		Session::flash('message', "Importación Exitosa");
		return redirect('economic_complement');
	}

	public function export_to_bank(Request $request)
	{
		global $year, $semester, $i, $afi, $semester1, $abv, $she;
		$year = $request->year;
		$semester = $request->semester;
		$afi = DB::table('eco_com_applicants')
			->select(DB::raw("economic_complements.id,economic_complements.affiliate_id,economic_complements.semester,cities0.second_shortened as regional,eco_com_applicants.identity_card,cities1.first_shortened as ext,concat_ws(' ', NULLIF(eco_com_applicants.first_name,null), NULLIF(eco_com_applicants.second_name, null), NULLIF(eco_com_applicants.last_name, null), NULLIF(eco_com_applicants.mothers_last_name, null), NULLIF(eco_com_applicants.surname_husband, null)) as full_name,economic_complements.total as importe,eco_com_modalities.shortened as modality,degrees.shortened as degree,categories.name as category"))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('cities as cities0', 'economic_complements.city_id', '=', 'cities0.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'eco_com_applicants.city_identity_card_id', '=', 'cities1.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('eco_com_procedures', 'economic_complements.eco_com_procedure_id', '=', 'eco_com_procedures.id')
			->whereYear('eco_com_procedures.year', '=', $year)
			->where('eco_com_procedures.semester', '=', $semester)
			->where('economic_complements.workflow_id', '=', 1)
			->where('economic_complements.wf_current_state_id', '=', 3)
			->where('economic_complements.state', 'Edited')
			->where('economic_complements.total', '>', 0)
			->whereRaw('economic_complements.total_rent::numeric < economic_complements.salary_quotable::numeric')
			->whereRaw("not exists(SELECT eco_com_observations.economic_complement_id FROM eco_com_observations
					WHERE economic_complements.id = eco_com_observations.economic_complement_id AND
				  	eco_com_observations.observation_type_id IN (1, 2, 6, 10, 13,22,26,30) AND
				  	eco_com_observations.is_enabled = FALSE AND eco_com_observations.deleted_at is null)")->get();

          //->whereNotNull('economic_complements.review_date')->get();     
     		// dd(sizeof($afi));

		if ($afi) {
			if ($semester == "Primer") {
				$semester1 = "MUSERPOL PAGO COMPLEMENTO ECONOMICO 1ER SEM " . $year;
				$abv = "Pago_Banco_Union_1ER_SEM_" . $year;
				$she = "BANCO_1ER_SEM" . $year;
			} else {
				$semester1 = "MUSERPOL PAGO COMPLEMENTO ECONOMICO 2DO SEM " . $year;
				$abv = "Export_for_Banco_Union_2DO_SEM_" . $year;
				$she = "BANCO_2DO_SEM" . $year;
			}
			Excel::create($abv, function ($excel) {
				global $year, $semester, $afi, $j, $semester1, $abv, $she;
				$j = 2;
				$excel->sheet($she . $year, function ($sheet) {
					$sheet->setColumnFormat(array(
						'D' => '#,##0.00' //1.000,10 (depende de windows)
                     // 'D' => \PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1  //1.000,10
					));
					global $year, $semester, $afi, $j, $i, $semester1;
					$i = 1;
					$sheet->row(1, array('DEPARTAMENTO', 'IDENTIFICACION', 'NOMBRE_Y_APELLIDO', 'IMPORTE_A_PAGAR', 'MONEDA_DEL_IMPORTE', 'DESCRIPCION_1', 'DESCRIPCION_2', 'DESCRIPCION_3'));

					foreach ($afi as $datos) {
						$economic = EconomicComplement::idIs($datos->id)->first();

                    //$import = number_format($datos->importe, 2, ',', '.');
						$import = $datos->importe;
						if ($economic->has_legal_guardian == true && $economic->has_legal_guardian_s == false) {

							$legal1 = EconomicComplementLegalGuardian::where('economic_complement_id', '=', $economic->id)->first();
							$sheet->row($j, array($datos->regional, $legal1->identity_card . " " . $legal1->city_identity_card->first_shortened, $legal1->getFullName(), $import, "1", $datos->modality . " - " . $datos->degree . " - " . $datos->category, $datos->affiliate_id, $semester1));

						} else {
							if ($economic->is_paid_spouse) {
								$spo = EconomicComplement::find($datos->id)->affiliate->spouse;
								$sheet->row($j, array($datos->regional, $spo->identity_card . " " . $spo->city_identity_card->first_shortened, $spo->getFullName(), $import, "1", $datos->modality . " - " . $datos->degree . " - " . $datos->category, $datos->affiliate_id, $semester1));
							}else{

								$apl = EconomicComplement::find($datos->id)->economic_complement_applicant;
								$sheet->row($j, array($datos->regional, $datos->identity_card . " " . $datos->ext, $apl->getFullName(), $import, "1", $datos->modality . " - " . $datos->degree . " - " . $datos->category, $datos->affiliate_id, $semester1));
							}

						}

						$j++;

					}

				});
			})->export('xls');
			return redirect('economic_complement');
			Session::flash('message', "Importación Exitosa");

		} else {

			Session::flash('message', "No existen registros para exportar");
			return redirect('economic_complement');

		}


	}
	public function export_to_bank_two(Request $request)
	{
		$eco_com_state_paid_bank = 24;
		global $year, $semester, $i, $afi, $semester1, $abv, $she;
		$year = $request->year;
		$semester = $request->semester;
		$afi = DB::table('eco_com_applicants')
			->select(DB::raw("economic_complements.id,economic_complements.affiliate_id,economic_complements.semester,cities0.second_shortened as regional,eco_com_applicants.identity_card,cities1.first_shortened as ext,concat_ws(' ', NULLIF(eco_com_applicants.first_name,null), NULLIF(eco_com_applicants.second_name, null), NULLIF(eco_com_applicants.last_name, null), NULLIF(eco_com_applicants.mothers_last_name, null), NULLIF(eco_com_applicants.surname_husband, null)) as full_name,economic_complements.total as importe,eco_com_modalities.shortened as modality,degrees.shortened as degree,categories.name as category"))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('cities as cities0', 'economic_complements.city_id', '=', 'cities0.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'eco_com_applicants.city_identity_card_id', '=', 'cities1.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('eco_com_procedures', 'economic_complements.eco_com_procedure_id', '=', 'eco_com_procedures.id')
			->whereYear('eco_com_procedures.year', '=', $year)
			->where('eco_com_procedures.semester', '=', $semester)
			->where('economic_complements.workflow_id', '=', 1)
			->where('economic_complements.wf_current_state_id', '=', 3)
			->where('economic_complements.state', 'Edited')
			->where('economic_complements.total', '>', 0)
			->whereRaw('economic_complements.total_rent::numeric < economic_complements.salary_quotable::numeric')
			->where('economic_complements.eco_com_state_id','!=' ,$eco_com_state_paid_bank)
			->whereRaw("not exists(SELECT eco_com_observations.economic_complement_id FROM eco_com_observations
					WHERE economic_complements.id = eco_com_observations.economic_complement_id AND
				  	eco_com_observations.observation_type_id IN (1, 2, 6, 10, 13,22,26,30) AND
				  	eco_com_observations.is_enabled = FALSE AND eco_com_observations.deleted_at is null)")->get();

          //->whereNotNull('economic_complements.review_date')->get();     
     		// dd(sizeof($afi));

		if ($afi) {
			if ($semester == "Primer") {
				$semester1 = "MUSERPOL PAGO COMPLEMENTO ECONOMICO 1ER SEM " . $year;
				$abv = "Pago_Banco_Union_1ER_SEM_" . $year;
				$she = "BANCO_1ER_SEM" . $year;
			} else {
				$semester1 = "MUSERPOL PAGO COMPLEMENTO ECONOMICO 2DO SEM " . $year;
				$abv = "Export_for_Banco_Union_2DO_SEM_" . $year;
				$she = "BANCO_2DO_SEM" . $year;
			}
			Excel::create($abv, function ($excel) {
				global $year, $semester, $afi, $j, $semester1, $abv, $she;
				$j = 2;
				$excel->sheet($she . $year, function ($sheet) {
					$sheet->setColumnFormat(array(
						'D' => '#,##0.00' //1.000,10 (depende de windows)
                     // 'D' => \PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1  //1.000,10
					));
					global $year, $semester, $afi, $j, $i, $semester1;
					$i = 1;
					$sheet->row(1, array('DEPARTAMENTO', 'IDENTIFICACION', 'NOMBRE_Y_APELLIDO', 'IMPORTE_A_PAGAR', 'MONEDA_DEL_IMPORTE', 'DESCRIPCION_1', 'DESCRIPCION_2', 'DESCRIPCION_3'));

					foreach ($afi as $datos) {
						$economic = EconomicComplement::idIs($datos->id)->first();

                    //$import = number_format($datos->importe, 2, ',', '.');
						$import = $datos->importe;
						if ($economic->has_legal_guardian == true && $economic->has_legal_guardian_s == false) {

							$legal1 = EconomicComplementLegalGuardian::where('economic_complement_id', '=', $economic->id)->first();
							$sheet->row($j, array($datos->regional, $legal1->identity_card . " " . $legal1->city_identity_card->first_shortened, $legal1->getFullName(), $import, "1", $datos->modality . " - " . $datos->degree . " - " . $datos->category, $datos->affiliate_id, $semester1));

						} else {
							if ($economic->is_paid_spouse) {
								$spo = EconomicComplement::find($datos->id)->affiliate->spouse;
								$sheet->row($j, array($datos->regional, $spo->identity_card . " " . $spo->city_identity_card->first_shortened, $spo->getFullName(), $import, "1", $datos->modality . " - " . $datos->degree . " - " . $datos->category, $datos->affiliate_id, $semester1));
							}else{

								$apl = EconomicComplement::find($datos->id)->economic_complement_applicant;
								$sheet->row($j, array($datos->regional, $datos->identity_card . " " . $datos->ext, $apl->getFullName(), $import, "1", $datos->modality . " - " . $datos->degree . " - " . $datos->category, $datos->affiliate_id, $semester1));
							}

						}

						$j++;

					}

				});
			})->export('xls');
			return redirect('economic_complement');
			Session::flash('message', "Importación Exitosa");

		} else {

			Session::flash('message', "No existen registros para exportar");
			return redirect('economic_complement');

		}


	}public function export_to_bank_three(Request $request)
	{
		$eco_com_state_paid_bank = [24,25];
		global $year, $semester, $i, $afi, $semester1, $abv, $she;
		$year = $request->year;
		$semester = $request->semester;
		$afi = DB::table('eco_com_applicants')
			->select(DB::raw("economic_complements.id,economic_complements.affiliate_id,economic_complements.semester,cities0.second_shortened as regional,eco_com_applicants.identity_card,cities1.first_shortened as ext,concat_ws(' ', NULLIF(eco_com_applicants.first_name,null), NULLIF(eco_com_applicants.second_name, null), NULLIF(eco_com_applicants.last_name, null), NULLIF(eco_com_applicants.mothers_last_name, null), NULLIF(eco_com_applicants.surname_husband, null)) as full_name,economic_complements.total as importe,eco_com_modalities.shortened as modality,degrees.shortened as degree,categories.name as category"))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('cities as cities0', 'economic_complements.city_id', '=', 'cities0.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'eco_com_applicants.city_identity_card_id', '=', 'cities1.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('eco_com_procedures', 'economic_complements.eco_com_procedure_id', '=', 'eco_com_procedures.id')
			->whereYear('eco_com_procedures.year', '=', $year)
			->where('eco_com_procedures.semester', '=', $semester)
			->where('economic_complements.workflow_id', '=', 1)
			->where('economic_complements.wf_current_state_id', '=', 3)
			->where('economic_complements.state', 'Edited')
			->where('economic_complements.total', '>', 0)
			->whereRaw('economic_complements.total_rent::numeric < economic_complements.salary_quotable::numeric')
			->whereNotIn('economic_complements.eco_com_state_id',$eco_com_state_paid_bank)
			->whereRaw("not exists(SELECT eco_com_observations.economic_complement_id FROM eco_com_observations
					WHERE economic_complements.id = eco_com_observations.economic_complement_id AND
				  	eco_com_observations.observation_type_id IN (1, 2, 6, 10, 13,22,26,30) AND
				  	eco_com_observations.is_enabled = FALSE AND eco_com_observations.deleted_at is null)")->get();

          //->whereNotNull('economic_complements.review_date')->get();     
     		// dd(sizeof($afi));

		if ($afi) {
			if ($semester == "Primer") {
				$semester1 = "MUSERPOL PAGO COMPLEMENTO ECONOMICO 1ER SEM " . $year;
				$abv = "Pago_Banco_Union_1ER_SEM_" . $year;
				$she = "BANCO_1ER_SEM" . $year;
			} else {
				$semester1 = "MUSERPOL PAGO COMPLEMENTO ECONOMICO 2DO SEM " . $year;
				$abv = "Export_for_Banco_Union_2DO_SEM_" . $year;
				$she = "BANCO_2DO_SEM" . $year;
			}
			Excel::create($abv, function ($excel) {
				global $year, $semester, $afi, $j, $semester1, $abv, $she;
				$j = 2;
				$excel->sheet($she . $year, function ($sheet) {
					$sheet->setColumnFormat(array(
						'D' => '#,##0.00' //1.000,10 (depende de windows)
                     // 'D' => \PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1  //1.000,10
					));
					global $year, $semester, $afi, $j, $i, $semester1;
					$i = 1;
					$sheet->row(1, array('DEPARTAMENTO', 'IDENTIFICACION', 'NOMBRE_Y_APELLIDO', 'IMPORTE_A_PAGAR', 'MONEDA_DEL_IMPORTE', 'DESCRIPCION_1', 'DESCRIPCION_2', 'DESCRIPCION_3'));

					foreach ($afi as $datos) {
						$economic = EconomicComplement::idIs($datos->id)->first();

                    //$import = number_format($datos->importe, 2, ',', '.');
						$import = $datos->importe;
						if ($economic->has_legal_guardian == true && $economic->has_legal_guardian_s == false) {

							$legal1 = EconomicComplementLegalGuardian::where('economic_complement_id', '=', $economic->id)->first();
							$sheet->row($j, array($datos->regional, $legal1->identity_card . " " . $legal1->city_identity_card->first_shortened, $legal1->getFullName(), $import, "1", $datos->modality . " - " . $datos->degree . " - " . $datos->category, $datos->affiliate_id, $semester1));

						} else {
							if ($economic->is_paid_spouse) {
								$spo = EconomicComplement::find($datos->id)->affiliate->spouse;
								$sheet->row($j, array($datos->regional, $spo->identity_card . " " . $spo->city_identity_card->first_shortened, $spo->getFullName(), $import, "1", $datos->modality . " - " . $datos->degree . " - " . $datos->category, $datos->affiliate_id, $semester1));
							}else{

								$apl = EconomicComplement::find($datos->id)->economic_complement_applicant;
								$sheet->row($j, array($datos->regional, $datos->identity_card . " " . $datos->ext, $apl->getFullName(), $import, "1", $datos->modality . " - " . $datos->degree . " - " . $datos->category, $datos->affiliate_id, $semester1));
							}

						}

						$j++;

					}

				});
			})->export('xls');
			return redirect('economic_complement');
			Session::flash('message', "Importación Exitosa");

		} else {

			Session::flash('message', "No existen registros para exportar");
			return redirect('economic_complement');

		}


	}

    /* David */
	public function export_excel()
	{
		if (Auth::check()) {
			$user_role_id = Auth::user()->roles()->first();
			Log::info("user_role_id = " . $user_role_id->id);
			$semestre = DB::table('eco_com_procedures')->orderBy('id', 'DESC')->first();

			$economic_complements = EconomicComplement::where('eco_com_state_id', 16)
				->leftJoin('eco_com_applicants', 'economic_complements.id', '=', 'eco_com_applicants.economic_complement_id')
				->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
				->leftJoin('eco_com_procedures', 'economic_complements.eco_com_procedure_id', '=', 'eco_com_procedures.id')
				->leftJoin('cities', 'economic_complements.city_id', '=', 'cities.id')
				->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
				->leftJoin('base_wages', 'economic_complements.base_wage_id', '=', 'base_wages.id')
				->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
				->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
				->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
				->leftJoin('affiliate_observations', 'affiliates.id', '=', 'affiliate_observations.affiliate_id')
				->where('economic_complements.workflow_id', '=', '1')
				->where('economic_complements.wf_current_state_id', '2')
				->where('economic_complements.state', 'Edited')
				->where('economic_complements.eco_com_procedure_id', '2')
				->select('economic_complements.review_date as Fecha', 'eco_com_applicants.identity_card as CI', 'cities.first_shortened as Exp_complemento', 'eco_com_applicants.first_name as Primer_nombre', 'eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno', 'eco_com_applicants.mothers_last_name as Materno', 'eco_com_applicants.surname_husband as ap_esp', 'eco_com_applicants.birth_date as Fecha_nac', 'eco_com_applicants.nua', 'eco_com_applicants.phone_number as Telefono', 'eco_com_applicants.cell_phone_number as celular', 'eco_com_modalities.shortened as tipo_renta', 'eco_com_procedures.year as año_gestion', 'eco_com_procedures.semester as semestre', 'categories.name as categoria', 'degrees.shortened as Grado', 'base_wages.amount as Sueldo_base', 'economic_complements.code as Nro_proceso', 'pension_entities.name as Ente_gestor', 'affiliate_observations.date as Fecha_obs', 'affiliate_observations.message as Observacion')
				->orderBy('economic_complements.review_date', 'ASC')
				->get();

			Excel::create('Reporte General ' . date("Y-m-d H:i:s"), function ($excel) use ($economic_complements) {


				$excel->sheet('Reporte General', function ($sheet) use ($economic_complements) {

					$sheet->fromArray($economic_complements);

				});

			})->download('xls');

		} else {
			return "funcion no disponible revise su sesion de usuario";
		}
	}
	public function export_excel_user()
	{


        // $complementos = EconomicComplement::where('workflow_id','=','1')
        //                               // ->where('wf_current_state_id','=','2')
        //                               ->where('state','=','Edited')
        //                               ->get();   
		if (Auth::check()) {
			$user_role_id = Auth::user()->roles()->first();
        //Log::info("user_role_id = ".$user_role_id->id);
			$semestre = DB::table('eco_com_procedures')->orderBy('id', 'DESC')->first();
      //  Log::info($semestre->id);
			$economic_complements = EconomicComplement::where('eco_com_state_id', 16)
				->leftJoin('eco_com_applicants', 'economic_complements.id', '=', 'eco_com_applicants.economic_complement_id')
				->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
				->leftJoin('eco_com_procedures', 'economic_complements.eco_com_procedure_id', '=', 'eco_com_procedures.id')
				->leftJoin('cities', 'economic_complements.city_id', '=', 'cities.id')
				->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
				->leftJoin('base_wages', 'economic_complements.base_wage_id', '=', 'base_wages.id')
				->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')

				->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
				->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
				->leftJoin('affiliate_observations', 'affiliates.id', '=', 'affiliate_observations.affiliate_id')

				->where('economic_complements.workflow_id', '=', '1')
				->where('economic_complements.wf_current_state_id', '2')
				->where('economic_complements.state', 'Edited')
				->where('economic_complements.eco_com_procedure_id', '2')
				->where('economic_complements.user_id', Auth::user()->id)


				->select('economic_complements.review_date as Fecha', 'eco_com_applicants.identity_card as CI', 'cities.first_shortened as Exp', 'eco_com_applicants.first_name as Primer_nombre', 'eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno', 'eco_com_applicants.mothers_last_name as Materno', 'eco_com_applicants.surname_husband as ap_esp', 'eco_com_applicants.birth_date as Fecha_nac', 'eco_com_applicants.nua', 'eco_com_applicants.phone_number as Telefono', 'eco_com_applicants.cell_phone_number as celular', 'eco_com_modalities.shortened as tipo_renta', 'eco_com_procedures.year as año_gestion', 'eco_com_procedures.semester as semestre', 'categories.name as categoria', 'degrees.shortened as Grado', 'base_wages.amount as Sueldo_base', 'economic_complements.code as Nro_proceso', 'pension_entities.name as Ente_gestor', 'affiliate_observations.date as Fecha_obs', 'affiliate_observations.message as Observacion')
				->orderBy('economic_complements.review_date', 'ASC')
           // ->select('economic_complements.id as id_base' ,'economic_complements.code as codigo')
				->get();

       //  return $economic_complements;
        //$fila = new CustomCollection(array('identificador' => ,$economic_complements-> ));
			Excel::create('Reporte ' . date("Y-m-d H:i:s") . ' - ' . Auth::user()->first_name . ' ' . Auth::user()->last_name, function ($excel) use ($economic_complements) {


				$excel->sheet('Reporte usuario', function ($sheet) use ($economic_complements) {

					$sheet->fromArray($economic_complements);
                        // $sheet->fromArray(
                        //                     array(
                        //                            $rows
                        //                           )
                        //                   );

                          // $sheet->row(1,array('Contribuciones: '.$contribuciones->count(),'Total Bs: '.$total) );

                          // $sheet->cells('A1:B1', function($cells) {
                          // $cells->setBackground('#4CCCD4');
                                                      // manipulate the range of cells

				});

			})->download('xls');

        //return $economic_complements;
       // return "contribuciones totales ".$economic_complements->count();
		} else {
			return "funcion no disponible revise su sesion de usuario";
		}
	}
	public function export_excel_general()
	{
      // $complementos = EconomicComplement::where('workflow_id','=','1')
        //                               // ->where('wf_current_state_id','=','2')
        //                               ->where('state','=','Edited')
        //                               ->get();   
		if (Auth::check()) {
			$user_role_id = Auth::user()->roles()->first();
			Log::info("user_role_id = " . $user_role_id->id);
			$semestre = DB::table('eco_com_procedures')->orderBy('id', 'DESC')->first();

			$economic_complements = EconomicComplement::whereNotNull('total_rent')
				->leftJoin('eco_com_applicants', 'economic_complements.id', '=', 'eco_com_applicants.economic_complement_id')
				->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
				->leftJoin('eco_com_procedures', 'economic_complements.eco_com_procedure_id', '=', 'eco_com_procedures.id')
				->leftJoin('cities', 'economic_complements.city_id', '=', 'cities.id')
				->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
				->leftJoin('base_wages', 'economic_complements.base_wage_id', '=', 'base_wages.id')
				->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')

				->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
				->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
				->leftJoin('affiliate_observations', 'affiliates.id', '=', 'affiliate_observations.affiliate_id')

            // ->where('economic_complements.workflow_id','=','1')
            // ->where('economic_complements.wf_current_state_id','2')
            // ->where('economic_complements.state','Edited')
          //  ->where('economic_complements.eco_com_procedure_id',$semestre->id)
				->where('economic_complements.eco_com_procedure_id', '2')
            //->where('economic_complements.user_id',Auth::user()->id)

				->distinct('economic_complements.id')
				->select('economic_complements.id', 'eco_com_applicants.identity_card as CI', 'cities.first_shortened as Exp', 'eco_com_applicants.first_name as Primer_nombre', 'eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno', 'eco_com_applicants.mothers_last_name as Materno', 'eco_com_applicants.surname_husband as ap_esp', 'eco_com_applicants.birth_date as Fecha_nac', 'eco_com_applicants.nua', 'eco_com_applicants.phone_number as Telefono', 'eco_com_applicants.cell_phone_number as celular', 'eco_com_modalities.shortened as tipo_renta', 'eco_com_procedures.year as año_gestion', 'eco_com_procedures.semester as semestre', 'categories.name as categoria', 'degrees.shortened as Grado', 'economic_complements.total_rent as Renta_total', 'base_wages.amount as Sueldo_base', 'economic_complements.seniority as antiguedad', 'economic_complements.salary_quotable as Salario_cotizable', 'economic_complements.difference as direfencia', 'economic_complements.total_amount_semester as monto_total_semestre', 'economic_complements.complementary_factor as factor_de_complementacion', 'economic_complements.total', 'economic_complements.code as Nro_proceso', 'pension_entities.name as Ente_gestor', 'affiliate_observations.date as Fecha_obs', 'affiliate_observations.message as Observacion')
           // ->select('economic_complements.id as id_base' ,'economic_complements.code as codigo')
            // ->orderBy('economic_complements.review_date','ASC')
				->get();

       //  return $economic_complements;
        //$fila = new CustomCollection(array('identificador' => ,$economic_complements-> ));
			Excel::create('Reporte General ' . date("Y-m-d H:i:s"), function ($excel) use ($economic_complements) {


				$excel->sheet('Reporte General Complemento', function ($sheet) use ($economic_complements) {

					$sheet->fromArray($economic_complements);
                        // $sheet->fromArray(
                        //                     array(
                        //                            $rows
                        //                           )
                        //                   );

                          // $sheet->row(1,array('Contribuciones: '.$contribuciones->count(),'Total Bs: '.$total) );

                          // $sheet->cells('A1:B1', function($cells) {
                          // $cells->setBackground('#4CCCD4');
                                                      // manipulate the range of cells

				});

			})->download('xls');

        //return $economic_complements;
       // return "contribuciones totales ".$economic_complements->count();
		} else {
			return "funcion no disponible revise su sesion de usuario";
		}
	}

	public function export_excel_observations()
	{
		if (Auth::check()) {


			global $com_obser_contabilidad_1, $com_obser_prestamos_2, $com_obser_juridica_3, $com_obser_fueraplz90_4, $com_obser_fueraplz120_5, $com_obser_faltareq_6, $com_obser_habitualinclusion7, $com_obser_menor16anos_8, $com_obser_invalidez_9, $com_obser_salario_10, $com_obser_pagodomicilio_12, $com_obser_repofond_13, $com_obser_legalguardian;



			$com_obser_contabilidad_1 = array();
			$com_obser_prestamos_2 = array();
			$com_obser_juridica_3 = array();
			$com_obser_fueraplz90_4 = array();
			$com_obser_fueraplz120_5 = array();
			$com_obser_faltareq_6 = array();
			$com_obser_habitualinclusion7 = array();
			$com_obser_menor16anos_8 = array();
			$com_obser_invalidez_9 = array();
			$com_obser_salario_10 = array();
			$com_obser_pagodomicilio_12 = array();
			$com_obser_repofond_13 = array();
			$com_obser_legalguardian = array();


      
      
        // $afiliados = DB::table('v_observados')->whereIn('id',$a)->get();
			$observados_prestamos = DB::table('affiliate_observations')
				->join('economic_complements', 'economic_complements.affiliate_id', '=', 'affiliate_observations.affiliate_id')
				->where('affiliate_observations.observation_type_id', 2)
                     // ->whereIn('affiliate_observations.observation_type_id',[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15])
				->where('economic_complements.eco_com_procedure_id', 6)
				->where('economic_complements.workflow_id', '<=', 3)
				->whereNull('affiliate_observations.deleted_at')
				->select(DB::raw('DISTINCT ON (affiliate_observations.affiliate_id) affiliate_observations.affiliate_id as id'), 'affiliate_observations.observation_type_id', 'economic_complements.id as complemento_id')
				->get();

			foreach ($observados_prestamos as $afiliado) {
          # code...
				array_push($com_obser_prestamos_2, $afiliado->complemento_id);
			}

			$observados_contabilidad = DB::table('affiliate_observations')
				->join('economic_complements', 'economic_complements.affiliate_id', '=', 'affiliate_observations.affiliate_id')
				->where('affiliate_observations.observation_type_id', 1)
                     // ->whereIn('affiliate_observations.observation_type_id',[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15])
				->where('economic_complements.eco_com_procedure_id', 6)
				->where('economic_complements.workflow_id', '<=', 3)
				->whereNull('affiliate_observations.deleted_at')
				->select(DB::raw('DISTINCT ON (affiliate_observations.affiliate_id) affiliate_observations.affiliate_id as id'), 'affiliate_observations.observation_type_id', 'economic_complements.id as complemento_id')
				->get();
			foreach ($observados_contabilidad as $afiliado) {
          # code...
				array_push($com_obser_contabilidad_1, $afiliado->complemento_id);
			}

			$observados_juridica = DB::table('affiliate_observations')
				->join('economic_complements', 'economic_complements.affiliate_id', '=', 'affiliate_observations.affiliate_id')
				->where('affiliate_observations.observation_type_id', 3)
                     // ->whereIn('affiliate_observations.observation_type_id',[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15])
				->where('economic_complements.eco_com_procedure_id', 6)
				->where('economic_complements.workflow_id', '<=', 3)
				->whereNull('affiliate_observations.deleted_at')
				->select(DB::raw('DISTINCT ON (affiliate_observations.affiliate_id) affiliate_observations.affiliate_id as id'), 'affiliate_observations.observation_type_id', 'economic_complements.id as complemento_id')
				->get();
			foreach ($observados_juridica as $afiliado) {
          # code...
				array_push($com_obser_juridica_3, $afiliado->complemento_id);
			}

			$observados_fueraplz90_4 = DB::table('affiliate_observations')
				->join('economic_complements', 'economic_complements.affiliate_id', '=', 'affiliate_observations.affiliate_id')
				->where('affiliate_observations.observation_type_id', 4)
                     // ->whereIn('affiliate_observations.observation_type_id',[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15])
				->where('economic_complements.eco_com_procedure_id', 6)
				->where('economic_complements.workflow_id', '<=', 3)
				->whereNull('affiliate_observations.deleted_at')
				->select(DB::raw('DISTINCT ON (affiliate_observations.affiliate_id) affiliate_observations.affiliate_id as id'), 'affiliate_observations.observation_type_id', 'economic_complements.id as complemento_id')
				->get();
			foreach ($observados_fueraplz90_4 as $afiliado) {
          # code...
				array_push($com_obser_fueraplz90_4, $afiliado->complemento_id);
			}

			$observados_fueraplz120_5 = DB::table('affiliate_observations')
				->join('economic_complements', 'economic_complements.affiliate_id', '=', 'affiliate_observations.affiliate_id')
				->where('affiliate_observations.observation_type_id', 5)
                     // ->whereIn('affiliate_observations.observation_type_id',[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15])
				->where('economic_complements.eco_com_procedure_id', 6)
				->where('economic_complements.workflow_id', '<=', 3)
				->whereNull('affiliate_observations.deleted_at')
				->select(DB::raw('DISTINCT ON (affiliate_observations.affiliate_id) affiliate_observations.affiliate_id as id'), 'affiliate_observations.observation_type_id', 'economic_complements.id as complemento_id')
				->get();
			foreach ($observados_fueraplz120_5 as $afiliado) {
          # code...
				array_push($com_obser_fueraplz120_5, $afiliado->complemento_id);
			}

			$observados_faltareq_6 = DB::table('affiliate_observations')
				->join('economic_complements', 'economic_complements.affiliate_id', '=', 'affiliate_observations.affiliate_id')
				->where('affiliate_observations.observation_type_id', 6)
                     // ->whereIn('affiliate_observations.observation_type_id',[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15])
				->where('economic_complements.eco_com_procedure_id', 6)
				->where('economic_complements.workflow_id', '<=', 3)
				->whereNull('affiliate_observations.deleted_at')
				->select(DB::raw('DISTINCT ON (affiliate_observations.affiliate_id) affiliate_observations.affiliate_id as id'), 'affiliate_observations.observation_type_id', 'economic_complements.id as complemento_id')
				->get();
			foreach ($observados_faltareq_6 as $afiliado) {
          # code...
				array_push($com_obser_faltareq_6, $afiliado->complemento_id);
			}

			$observados_habitualinclusion7 = DB::table('affiliate_observations')
				->join('economic_complements', 'economic_complements.affiliate_id', '=', 'affiliate_observations.affiliate_id')
				->where('affiliate_observations.observation_type_id', 7)
                     // ->whereIn('affiliate_observations.observation_type_id',[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15])
				->where('economic_complements.eco_com_procedure_id', 6)
				->where('economic_complements.workflow_id', '<=', 3)
				->whereNull('affiliate_observations.deleted_at')
				->select(DB::raw('DISTINCT ON (affiliate_observations.affiliate_id) affiliate_observations.affiliate_id as id'), 'affiliate_observations.observation_type_id', 'economic_complements.id as complemento_id')
				->get();
			foreach ($observados_habitualinclusion7 as $afiliado) {
          # code...
				array_push($com_obser_habitualinclusion7, $afiliado->complemento_id);
			}

			$observados_menor16anos_8 = DB::table('affiliate_observations')
				->join('economic_complements', 'economic_complements.affiliate_id', '=', 'affiliate_observations.affiliate_id')
				->where('affiliate_observations.observation_type_id', 8)
                     // ->whereIn('affiliate_observations.observation_type_id',[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15])
				->where('economic_complements.eco_com_procedure_id', 6)
				->where('economic_complements.workflow_id', '<=', 3)
				->whereNull('affiliate_observations.deleted_at')
				->select(DB::raw('DISTINCT ON (affiliate_observations.affiliate_id) affiliate_observations.affiliate_id as id'), 'affiliate_observations.observation_type_id', 'economic_complements.id as complemento_id')
				->get();
			foreach ($observados_menor16anos_8 as $afiliado) {
          # code...
				array_push($com_obser_menor16anos_8, $afiliado->complemento_id);
			}

			$observados_invalidez_9 = DB::table('affiliate_observations')
				->join('economic_complements', 'economic_complements.affiliate_id', '=', 'affiliate_observations.affiliate_id')
				->where('affiliate_observations.observation_type_id', 9)
                     // ->whereIn('affiliate_observations.observation_type_id',[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15])
				->where('economic_complements.eco_com_procedure_id', 6)
				->where('economic_complements.workflow_id', '<=', 3)
				->whereNull('affiliate_observations.deleted_at')
				->select(DB::raw('DISTINCT ON (affiliate_observations.affiliate_id) affiliate_observations.affiliate_id as id'), 'affiliate_observations.observation_type_id', 'economic_complements.id as complemento_id')
				->get();
			foreach ($observados_invalidez_9 as $afiliado) {
          # code...
				array_push($com_obser_invalidez_9, $afiliado->complemento_id);
			}

			$observados_salario_10 = DB::table('affiliate_observations')
				->join('economic_complements', 'economic_complements.affiliate_id', '=', 'affiliate_observations.affiliate_id')
				->where('affiliate_observations.observation_type_id', 10)
                     // ->whereIn('affiliate_observations.observation_type_id',[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15])
				->where('economic_complements.eco_com_procedure_id', 6)
				->where('economic_complements.workflow_id', '<=', 3)
				->whereNull('affiliate_observations.deleted_at')
				->select(DB::raw('DISTINCT ON (affiliate_observations.affiliate_id) affiliate_observations.affiliate_id as id'), 'affiliate_observations.observation_type_id', 'economic_complements.id as complemento_id')
				->get();
			foreach ($observados_salario_10 as $afiliado) {
          # code...
				array_push($com_obser_salario_10, $afiliado->complemento_id);
			}

			$observados_pagodomicilio_12 = DB::table('affiliate_observations')
				->join('economic_complements', 'economic_complements.affiliate_id', '=', 'affiliate_observations.affiliate_id')
				->where('affiliate_observations.observation_type_id', 12)
                     // ->whereIn('affiliate_observations.observation_type_id',[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15])
				->where('economic_complements.eco_com_procedure_id', 6)
				->where('economic_complements.workflow_id', '<=', 3)
				->whereNull('affiliate_observations.deleted_at')
				->select(DB::raw('DISTINCT ON (affiliate_observations.affiliate_id) affiliate_observations.affiliate_id as id'), 'affiliate_observations.observation_type_id', 'economic_complements.id as complemento_id')
				->get();
			foreach ($observados_pagodomicilio_12 as $afiliado) {
          # code...
				array_push($com_obser_pagodomicilio_12, $afiliado->complemento_id);
			}

			$observados_repofond_13 = DB::table('affiliate_observations')
				->join('economic_complements', 'economic_complements.affiliate_id', '=', 'affiliate_observations.affiliate_id')
				->where('affiliate_observations.observation_type_id', 13)
                     // ->whereIn('affiliate_observations.observation_type_id',[1,2,3,4,5,6,7,8,9,10,11,12,13,14,15])
				->where('economic_complements.eco_com_procedure_id', 6)
				->where('economic_complements.workflow_id', '<=', 3)
				->whereNull('affiliate_observations.deleted_at')
				->select(DB::raw('DISTINCT ON (affiliate_observations.affiliate_id) affiliate_observations.affiliate_id as id'), 'affiliate_observations.observation_type_id', 'economic_complements.id as complemento_id')
				->get();
			foreach ($observados_repofond_13 as $afiliado) {
          # code...
				array_push($com_obser_repofond_13, $afiliado->complemento_id);
			}

			$observados_legalguardian = DB::table('economic_complements')
				->where('eco_com_procedure_id', 6)
				->where('workflow_id', '<=', 3)
				->where('has_legal_guardian', '=', 'true')
				->get();


			foreach ($observados_legalguardian as $afiliado) {
          # code...
				array_push($com_obser_legalguardian, $afiliado->id);
			}

        //dd($com_obser_legalguardian);
        //dd(sizeof($afiliados));
        
   
        //Log::info($com_obser_prestamos_2);

        
      //  return $economic_complements;
      //$fila = new CustomCollection(array('identificador' => ,$economic_complements-> ));
			Excel::create('Reporte General' . date("Y-m-d H:i:s"), function ($excel) {
				global $com_obser_contabilidad_1, $com_obser_prestamos_2, $com_obser_juridica_3, $com_obser_fueraplz90_4, $com_obser_fueraplz120_5, $com_obser_faltareq_6, $com_obser_habitualinclusion7, $com_obser_menor16anos_8, $com_obser_invalidez_9, $com_obser_salario_10, $com_obser_pagodomicilio_12, $com_obser_repofond_13;

				$excel->sheet('Observacion por contabilidad ', function ($sheet) {

					global $com_obser_contabilidad_1, $com_obser_prestamos_2, $com_obser_juridica_3, $com_obser_fueraplz90_4, $com_obser_fueraplz120_5, $com_obser_faltareq_6, $com_obser_habitualinclusion7, $com_obser_menor16anos_8, $com_obser_invalidez_9, $com_obser_salario_10, $com_obser_pagodomicilio_12, $com_obser_repofond_13;
					$economic_complements = EconomicComplement::whereIn('economic_complements.id', $com_obser_contabilidad_1)
						->leftJoin('eco_com_applicants', 'economic_complements.id', '=', 'eco_com_applicants.economic_complement_id')
						->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
						->leftJoin('cities as city_com', 'economic_complements.city_id', '=', 'city_com.id')
						->leftJoin('cities as city_ben', 'eco_com_applicants.city_identity_card_id', '=', 'city_ben.id')
						->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
						->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
						->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
						->distinct('economic_complements.id')
						->select('economic_complements.id as Id', 'economic_complements.code as Nro_tramite', 'eco_com_applicants.first_name as Primer_nombre', 'eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno', 'eco_com_applicants.mothers_last_name as Materno', 'eco_com_applicants.identity_card as CI', 'city_ben.first_shortened as Ext', 'city_com.name as Regional', 'degrees.shortened as Grado', 'eco_com_modalities.shortened as Tipo_renta', 'economic_complements.total as Complemento_Final', 'affiliates.id as affiliate_id')
						->get();

					$rows = array(array('ID', 'Nro de Tramite', 'Nombres y Apellidos', 'C.I.', 'Ext', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Económico Final', 'Observaciones'));
					foreach ($economic_complements as $c) {
                          # code...
						$observaciones = DB::table('affiliate_observations')->where('affiliate_id', $c->affiliate_id)->get();
						$observacion = "";
						foreach ($observaciones as $obs) {
                            # code...
							$observacion = $observacion . " | " . $obs->message;
						}

						array_push($rows, array($c->Id, $c->Nro_tramite, $c->Primer_nombre . ' ' . $c->Segundo_nombre . ' ' . $c->Paterno . ' ' . $c->Materno, $c->CI, $c->Ext, $c->Regional, $c->Grado, $c->Tipo_renta, $c->Complemento_Final, $observacion));
					}

					$sheet->fromArray($rows, null, 'A1', false, false);
					$sheet->cells('A1:J1', function ($cells) {

                            // manipulate the range of cells
						$cells->setBackground('#058A37');
						$cells->setFontColor('#ffffff');

					});

				});

				$excel->sheet('Observacion por prestamos ', function ($sheet) {

					global $com_obser_contabilidad_1, $com_obser_prestamos_2, $com_obser_juridica_3, $com_obser_fueraplz90_4, $com_obser_fueraplz120_5, $com_obser_faltareq_6, $com_obser_habitualinclusion7, $com_obser_menor16anos_8, $com_obser_invalidez_9, $com_obser_salario_10, $com_obser_pagodomicilio_12, $com_obser_repofond_13;
					$economic_complements = EconomicComplement::whereIn('economic_complements.id', $com_obser_prestamos_2)
						->leftJoin('eco_com_applicants', 'economic_complements.id', '=', 'eco_com_applicants.economic_complement_id')
						->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
						->leftJoin('cities as city_com', 'economic_complements.city_id', '=', 'city_com.id')
						->leftJoin('cities as city_ben', 'eco_com_applicants.city_identity_card_id', '=', 'city_ben.id')
						->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
						->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
						->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
						->distinct('economic_complements.id')
						->select('economic_complements.id as Id', 'economic_complements.code as Nro_tramite', 'eco_com_applicants.first_name as Primer_nombre', 'eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno', 'eco_com_applicants.mothers_last_name as Materno', 'eco_com_applicants.identity_card as CI', 'city_ben.first_shortened as Ext', 'city_com.name as Regional', 'degrees.shortened as Grado', 'eco_com_modalities.shortened as Tipo_renta', 'economic_complements.total as Complemento_Final', 'affiliates.id as affiliate_id')
						->get();

					$rows = array(array('ID', 'Nro de Tramite', 'Nombres y Apellidos', 'C.I.', 'Ext', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Económico Final', 'Observaciones'));
					foreach ($economic_complements as $c) {
                          # code...
						$observaciones = DB::table('affiliate_observations')->where('affiliate_id', $c->affiliate_id)->get();
						$observacion = "";
						foreach ($observaciones as $obs) {
                            # code...
							$observacion = $observacion . " | " . $obs->message;
						}

						array_push($rows, array($c->Id, $c->Nro_tramite, $c->Primer_nombre . ' ' . $c->Segundo_nombre . ' ' . $c->Paterno . ' ' . $c->Materno, $c->CI, $c->Ext, $c->Regional, $c->Grado, $c->Tipo_renta, $c->Complemento_Final, $observacion));
					}

					$sheet->fromArray($rows, null, 'A1', false, false);
					$sheet->cells('A1:J1', function ($cells) {

                            // manipulate the range of cells
						$cells->setBackground('#058A37');
						$cells->setFontColor('#ffffff');

					});

				});

				$excel->sheet('Observacion por juridica ', function ($sheet) {

					global $com_obser_contabilidad_1, $com_obser_prestamos_2, $com_obser_juridica_3, $com_obser_fueraplz90_4, $com_obser_fueraplz120_5, $com_obser_faltareq_6, $com_obser_habitualinclusion7, $com_obser_menor16anos_8, $com_obser_invalidez_9, $com_obser_salario_10, $com_obser_pagodomicilio_12, $com_obser_repofond_13;
					$economic_complements = EconomicComplement::whereIn('economic_complements.id', $com_obser_juridica_3)
						->leftJoin('eco_com_applicants', 'economic_complements.id', '=', 'eco_com_applicants.economic_complement_id')
						->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
						->leftJoin('cities as city_com', 'economic_complements.city_id', '=', 'city_com.id')
						->leftJoin('cities as city_ben', 'eco_com_applicants.city_identity_card_id', '=', 'city_ben.id')
						->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
						->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
						->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
						->distinct('economic_complements.id')
						->select('economic_complements.id as Id', 'economic_complements.code as Nro_tramite', 'eco_com_applicants.first_name as Primer_nombre', 'eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno', 'eco_com_applicants.mothers_last_name as Materno', 'eco_com_applicants.identity_card as CI', 'city_ben.first_shortened as Ext', 'city_com.name as Regional', 'degrees.shortened as Grado', 'eco_com_modalities.shortened as Tipo_renta', 'economic_complements.total as Complemento_Final', 'affiliates.id as affiliate_id')
						->get();

					$rows = array(array('ID', 'Nro de Tramite', 'Nombres y Apellidos', 'C.I.', 'Ext', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Económico Final', 'Observaciones'));
					foreach ($economic_complements as $c) {
                          # code...
						$observaciones = DB::table('affiliate_observations')->where('affiliate_id', $c->affiliate_id)->get();
						$observacion = "";
						foreach ($observaciones as $obs) {
                            # code...
							$observacion = $observacion . " | " . $obs->message;
						}

						array_push($rows, array($c->Id, $c->Nro_tramite, $c->Primer_nombre . ' ' . $c->Segundo_nombre . ' ' . $c->Paterno . ' ' . $c->Materno, $c->CI, $c->Ext, $c->Regional, $c->Grado, $c->Tipo_renta, $c->Complemento_Final, $observacion));
					}

					$sheet->fromArray($rows, null, 'A1', false, false);
					$sheet->cells('A1:J1', function ($cells) {

                            // manipulate the range of cells
						$cells->setBackground('#058A37');
						$cells->setFontColor('#ffffff');

					});

				});


				$excel->sheet('Fuera de Plazo 90 días', function ($sheet) {

					global $com_obser_contabilidad_1, $com_obser_prestamos_2, $com_obser_juridica_3, $com_obser_fueraplz90_4, $com_obser_fueraplz120_5, $com_obser_faltareq_6, $com_obser_habitualinclusion7, $com_obser_menor16anos_8, $com_obser_invalidez_9, $com_obser_salario_10, $com_obser_pagodomicilio_12, $com_obser_repofond_13;
					$economic_complements = EconomicComplement::whereIn('economic_complements.id', $com_obser_fueraplz90_4)
						->leftJoin('eco_com_applicants', 'economic_complements.id', '=', 'eco_com_applicants.economic_complement_id')
						->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
						->leftJoin('cities as city_com', 'economic_complements.city_id', '=', 'city_com.id')
						->leftJoin('cities as city_ben', 'eco_com_applicants.city_identity_card_id', '=', 'city_ben.id')
						->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
						->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
						->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
						->distinct('economic_complements.id')
						->select('economic_complements.id as Id', 'economic_complements.code as Nro_tramite', 'eco_com_applicants.first_name as Primer_nombre', 'eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno', 'eco_com_applicants.mothers_last_name as Materno', 'eco_com_applicants.identity_card as CI', 'city_ben.first_shortened as Ext', 'city_com.name as Regional', 'degrees.shortened as Grado', 'eco_com_modalities.shortened as Tipo_renta', 'economic_complements.total as Complemento_Final', 'affiliates.id as affiliate_id')
						->get();

					$rows = array(array('ID', 'Nro de Tramite', 'Nombres y Apellidos', 'C.I.', 'Ext', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Económico Final', 'Observaciones'));
					foreach ($economic_complements as $c) {
                          # code...
						$observaciones = DB::table('affiliate_observations')->where('affiliate_id', $c->affiliate_id)->get();
						$observacion = "";
						foreach ($observaciones as $obs) {
                            # code...
							$observacion = $observacion . " | " . $obs->message;
						}

						array_push($rows, array($c->Id, $c->Nro_tramite, $c->Primer_nombre . ' ' . $c->Segundo_nombre . ' ' . $c->Paterno . ' ' . $c->Materno, $c->CI, $c->Ext, $c->Regional, $c->Grado, $c->Tipo_renta, $c->Complemento_Final, $observacion));
					}

					$sheet->fromArray($rows, null, 'A1', false, false);
					$sheet->cells('A1:J1', function ($cells) {

                            // manipulate the range of cells
						$cells->setBackground('#058A37');
						$cells->setFontColor('#ffffff');

					});

				});

				$excel->sheet('Fuera de Plazo 120 días', function ($sheet) {

					global $com_obser_contabilidad_1, $com_obser_prestamos_2, $com_obser_juridica_3, $com_obser_fueraplz90_4, $com_obser_fueraplz120_5, $com_obser_faltareq_6, $com_obser_habitualinclusion7, $com_obser_menor16anos_8, $com_obser_invalidez_9, $com_obser_salario_10, $com_obser_pagodomicilio_12, $com_obser_repofond_13;
					$economic_complements = EconomicComplement::whereIn('economic_complements.id', $com_obser_fueraplz120_5)
						->leftJoin('eco_com_applicants', 'economic_complements.id', '=', 'eco_com_applicants.economic_complement_id')
						->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
						->leftJoin('cities as city_com', 'economic_complements.city_id', '=', 'city_com.id')
						->leftJoin('cities as city_ben', 'eco_com_applicants.city_identity_card_id', '=', 'city_ben.id')
						->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
						->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
						->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
						->distinct('economic_complements.id')
						->select('economic_complements.id as Id', 'economic_complements.code as Nro_tramite', 'eco_com_applicants.first_name as Primer_nombre', 'eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno', 'eco_com_applicants.mothers_last_name as Materno', 'eco_com_applicants.identity_card as CI', 'city_ben.first_shortened as Ext', 'city_com.name as Regional', 'degrees.shortened as Grado', 'eco_com_modalities.shortened as Tipo_renta', 'economic_complements.total as Complemento_Final', 'affiliates.id as affiliate_id')
						->get();

					$rows = array(array('ID', 'Nro de Tramite', 'Nombres y Apellidos', 'C.I.', 'Ext', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Económico Final', 'Observaciones'));
					foreach ($economic_complements as $c) {
                          # code...
						$observaciones = DB::table('affiliate_observations')->where('affiliate_id', $c->affiliate_id)->get();
						$observacion = "";
						foreach ($observaciones as $obs) {
                            # code...
							$observacion = $observacion . " | " . $obs->message;
						}

						array_push($rows, array($c->Id, $c->Nro_tramite, $c->Primer_nombre . ' ' . $c->Segundo_nombre . ' ' . $c->Paterno . ' ' . $c->Materno, $c->CI, $c->Ext, $c->Regional, $c->Grado, $c->Tipo_renta, $c->Complemento_Final, $observacion));
					}

					$sheet->fromArray($rows, null, 'A1', false, false);
					$sheet->cells('A1:J1', function ($cells) {

                            // manipulate the range of cells
						$cells->setBackground('#058A37');
						$cells->setFontColor('#ffffff');

					});

				});

				$excel->sheet('Falta de Requisitos', function ($sheet) {

					global $com_obser_contabilidad_1, $com_obser_prestamos_2, $com_obser_juridica_3, $com_obser_fueraplz90_4, $com_obser_fueraplz120_5, $com_obser_faltareq_6, $com_obser_habitualinclusion7, $com_obser_menor16anos_8, $com_obser_invalidez_9, $com_obser_salario_10, $com_obser_pagodomicilio_12, $com_obser_repofond_13;
					$economic_complements = EconomicComplement::whereIn('economic_complements.id', $com_obser_faltareq_6)
						->leftJoin('eco_com_applicants', 'economic_complements.id', '=', 'eco_com_applicants.economic_complement_id')
						->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
						->leftJoin('cities as city_com', 'economic_complements.city_id', '=', 'city_com.id')
						->leftJoin('cities as city_ben', 'eco_com_applicants.city_identity_card_id', '=', 'city_ben.id')
						->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
						->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
						->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
						->distinct('economic_complements.id')
						->select('economic_complements.id as Id', 'economic_complements.code as Nro_tramite', 'eco_com_applicants.first_name as Primer_nombre', 'eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno', 'eco_com_applicants.mothers_last_name as Materno', 'eco_com_applicants.identity_card as CI', 'city_ben.first_shortened as Ext', 'city_com.name as Regional', 'degrees.shortened as Grado', 'eco_com_modalities.shortened as Tipo_renta', 'economic_complements.total as Complemento_Final', 'affiliates.id as affiliate_id')
						->get();

					$rows = array(array('ID', 'Nro de Tramite', 'Nombres y Apellidos', 'C.I.', 'Ext', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Económico Final', 'Observaciones'));
					foreach ($economic_complements as $c) {
                          # code...
						$observaciones = DB::table('affiliate_observations')->where('affiliate_id', $c->affiliate_id)->get();
						$observacion = "";
						foreach ($observaciones as $obs) {
                            # code...
							$observacion = $observacion . " | " . $obs->message;
						}

						array_push($rows, array($c->Id, $c->Nro_tramite, $c->Primer_nombre . ' ' . $c->Segundo_nombre . ' ' . $c->Paterno . ' ' . $c->Materno, $c->CI, $c->Ext, $c->Regional, $c->Grado, $c->Tipo_renta, $c->Complemento_Final, $observacion));
					}

					$sheet->fromArray($rows, null, 'A1', false, false);
					$sheet->cells('A1:J1', function ($cells) {

                            // manipulate the range of cells
						$cells->setBackground('#058A37');
						$cells->setFontColor('#ffffff');

					});

				});


				$excel->sheet('Requisitos Hab a Incl', function ($sheet) {

					global $com_obser_contabilidad_1, $com_obser_prestamos_2, $com_obser_juridica_3, $com_obser_fueraplz90_4, $com_obser_fueraplz120_5, $com_obser_faltareq_6, $com_obser_habitualinclusion7, $com_obser_menor16anos_8, $com_obser_invalidez_9, $com_obser_salario_10, $com_obser_pagodomicilio_12, $com_obser_repofond_13;
					$economic_complements = EconomicComplement::whereIn('economic_complements.id', $com_obser_habitualinclusion7)
						->leftJoin('eco_com_applicants', 'economic_complements.id', '=', 'eco_com_applicants.economic_complement_id')
						->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
						->leftJoin('cities as city_com', 'economic_complements.city_id', '=', 'city_com.id')
						->leftJoin('cities as city_ben', 'eco_com_applicants.city_identity_card_id', '=', 'city_ben.id')
						->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
						->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
						->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
						->distinct('economic_complements.id')
						->select('economic_complements.id as Id', 'economic_complements.code as Nro_tramite', 'eco_com_applicants.first_name as Primer_nombre', 'eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno', 'eco_com_applicants.mothers_last_name as Materno', 'eco_com_applicants.identity_card as CI', 'city_ben.first_shortened as Ext', 'city_com.name as Regional', 'degrees.shortened as Grado', 'eco_com_modalities.shortened as Tipo_renta', 'economic_complements.total as Complemento_Final', 'affiliates.id as affiliate_id')
						->get();

					$rows = array(array('ID', 'Nro de Tramite', 'Nombres y Apellidos', 'C.I.', 'Ext', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Económico Final', 'Observaciones'));
					foreach ($economic_complements as $c) {
                          # code...
						$observaciones = DB::table('affiliate_observations')->where('affiliate_id', $c->affiliate_id)->get();
						$observacion = "";
						foreach ($observaciones as $obs) {
                            # code...
							$observacion = $observacion . " | " . $obs->message;
						}

						array_push($rows, array($c->Id, $c->Nro_tramite, $c->Primer_nombre . ' ' . $c->Segundo_nombre . ' ' . $c->Paterno . ' ' . $c->Materno, $c->CI, $c->Ext, $c->Regional, $c->Grado, $c->Tipo_renta, $c->Complemento_Final, $observacion));
					}

					$sheet->fromArray($rows, null, 'A1', false, false);
					$sheet->cells('A1:J1', function ($cells) {

                            // manipulate the range of cells
						$cells->setBackground('#058A37');
						$cells->setFontColor('#ffffff');

					});

				});

				$excel->sheet('Menor a 16 años', function ($sheet) {

					global $com_obser_contabilidad_1, $com_obser_prestamos_2, $com_obser_juridica_3, $com_obser_fueraplz90_4, $com_obser_fueraplz120_5, $com_obser_faltareq_6, $com_obser_habitualinclusion7, $com_obser_menor16anos_8, $com_obser_invalidez_9, $com_obser_salario_10, $com_obser_pagodomicilio_12, $com_obser_repofond_13;
					$economic_complements = EconomicComplement::whereIn('economic_complements.id', $com_obser_menor16anos_8)
						->leftJoin('eco_com_applicants', 'economic_complements.id', '=', 'eco_com_applicants.economic_complement_id')
						->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
						->leftJoin('cities as city_com', 'economic_complements.city_id', '=', 'city_com.id')
						->leftJoin('cities as city_ben', 'eco_com_applicants.city_identity_card_id', '=', 'city_ben.id')
						->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
						->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
						->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
						->distinct('economic_complements.id')
						->select('economic_complements.id as Id', 'economic_complements.code as Nro_tramite', 'eco_com_applicants.first_name as Primer_nombre', 'eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno', 'eco_com_applicants.mothers_last_name as Materno', 'eco_com_applicants.identity_card as CI', 'city_ben.first_shortened as Ext', 'city_com.name as Regional', 'degrees.shortened as Grado', 'eco_com_modalities.shortened as Tipo_renta', 'economic_complements.total as Complemento_Final', 'affiliates.id as affiliate_id')
						->get();

					$rows = array(array('ID', 'Nro de Tramite', 'Nombres y Apellidos', 'C.I.', 'Ext', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Económico Final', 'Observaciones'));
					foreach ($economic_complements as $c) {
                          # code...
						$observaciones = DB::table('affiliate_observations')->where('affiliate_id', $c->affiliate_id)->get();
						$observacion = "";
						foreach ($observaciones as $obs) {
                            # code...
							$observacion = $observacion . " | " . $obs->message;
						}

						array_push($rows, array($c->Id, $c->Nro_tramite, $c->Primer_nombre . ' ' . $c->Segundo_nombre . ' ' . $c->Paterno . ' ' . $c->Materno, $c->CI, $c->Ext, $c->Regional, $c->Grado, $c->Tipo_renta, $c->Complemento_Final, $observacion));
					}

					$sheet->fromArray($rows, null, 'A1', false, false);
					$sheet->cells('A1:J1', function ($cells) {

                            // manipulate the range of cells
						$cells->setBackground('#058A37');
						$cells->setFontColor('#ffffff');

					});

				});

				$excel->sheet('Observación por Invalidez', function ($sheet) {

					global $com_obser_contabilidad_1, $com_obser_prestamos_2, $com_obser_juridica_3, $com_obser_fueraplz90_4, $com_obser_fueraplz120_5, $com_obser_faltareq_6, $com_obser_habitualinclusion7, $com_obser_menor16anos_8, $com_obser_invalidez_9, $com_obser_salario_10, $com_obser_pagodomicilio_12, $com_obser_repofond_13;
					$economic_complements = EconomicComplement::whereIn('economic_complements.id', $com_obser_invalidez_9)
						->leftJoin('eco_com_applicants', 'economic_complements.id', '=', 'eco_com_applicants.economic_complement_id')
						->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
						->leftJoin('cities as city_com', 'economic_complements.city_id', '=', 'city_com.id')
						->leftJoin('cities as city_ben', 'eco_com_applicants.city_identity_card_id', '=', 'city_ben.id')
						->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
						->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
						->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
						->distinct('economic_complements.id')
						->select('economic_complements.id as Id', 'economic_complements.code as Nro_tramite', 'eco_com_applicants.first_name as Primer_nombre', 'eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno', 'eco_com_applicants.mothers_last_name as Materno', 'eco_com_applicants.identity_card as CI', 'city_ben.first_shortened as Ext', 'city_com.name as Regional', 'degrees.shortened as Grado', 'eco_com_modalities.shortened as Tipo_renta', 'economic_complements.total as Complemento_Final', 'affiliates.id as affiliate_id')
						->get();

					$rows = array(array('ID', 'Nro de Tramite', 'Nombres y Apellidos', 'C.I.', 'Ext', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Económico Final', 'Observaciones'));
					foreach ($economic_complements as $c) {
                          # code...
						$observaciones = DB::table('affiliate_observations')->where('affiliate_id', $c->affiliate_id)->get();
						$observacion = "";
						foreach ($observaciones as $obs) {
                            # code...
							$observacion = $observacion . " | " . $obs->message;
						}

						array_push($rows, array($c->Id, $c->Nro_tramite, $c->Primer_nombre . ' ' . $c->Segundo_nombre . ' ' . $c->Paterno . ' ' . $c->Materno, $c->CI, $c->Ext, $c->Regional, $c->Grado, $c->Tipo_renta, $c->Complemento_Final, $observacion));
					}

					$sheet->fromArray($rows, null, 'A1', false, false);
					$sheet->cells('A1:J1', function ($cells) {

                            // manipulate the range of cells
						$cells->setBackground('#058A37');
						$cells->setFontColor('#ffffff');

					});

				});

				$excel->sheet('Observación por Salario', function ($sheet) {

					global $com_obser_contabilidad_1, $com_obser_prestamos_2, $com_obser_juridica_3, $com_obser_fueraplz90_4, $com_obser_fueraplz120_5, $com_obser_faltareq_6, $com_obser_habitualinclusion7, $com_obser_menor16anos_8, $com_obser_invalidez_9, $com_obser_salario_10, $com_obser_pagodomicilio_12, $com_obser_repofond_13;
					$economic_complements = EconomicComplement::whereIn('economic_complements.id', $com_obser_salario_10)
						->leftJoin('eco_com_applicants', 'economic_complements.id', '=', 'eco_com_applicants.economic_complement_id')
						->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
						->leftJoin('cities as city_com', 'economic_complements.city_id', '=', 'city_com.id')
						->leftJoin('cities as city_ben', 'eco_com_applicants.city_identity_card_id', '=', 'city_ben.id')
						->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
						->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
						->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
						->distinct('economic_complements.id')
						->select('economic_complements.id as Id', 'economic_complements.code as Nro_tramite', 'eco_com_applicants.first_name as Primer_nombre', 'eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno', 'eco_com_applicants.mothers_last_name as Materno', 'eco_com_applicants.identity_card as CI', 'city_ben.first_shortened as Ext', 'city_com.name as Regional', 'degrees.shortened as Grado', 'eco_com_modalities.shortened as Tipo_renta', 'economic_complements.total as Complemento_Final', 'affiliates.id as affiliate_id')
						->get();

					$rows = array(array('ID', 'Nro de Tramite', 'Nombres y Apellidos', 'C.I.', 'Ext', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Económico Final', 'Observaciones'));
					foreach ($economic_complements as $c) {
                          # code...
						$observaciones = DB::table('affiliate_observations')->where('affiliate_id', $c->affiliate_id)->get();
						$observacion = "";
						foreach ($observaciones as $obs) {
                            # code...
							$observacion = $observacion . " | " . $obs->message;
						}

						array_push($rows, array($c->Id, $c->Nro_tramite, $c->Primer_nombre . ' ' . $c->Segundo_nombre . ' ' . $c->Paterno . ' ' . $c->Materno, $c->CI, $c->Ext, $c->Regional, $c->Grado, $c->Tipo_renta, $c->Complemento_Final, $observacion));
					}

					$sheet->fromArray($rows, null, 'A1', false, false);
					$sheet->cells('A1:J1', function ($cells) {

                            // manipulate the range of cells
						$cells->setBackground('#058A37');
						$cells->setFontColor('#ffffff');

					});

				});

				$excel->sheet('Pago a domicilio', function ($sheet) {

					global $com_obser_contabilidad_1, $com_obser_prestamos_2, $com_obser_juridica_3, $com_obser_fueraplz90_4, $com_obser_fueraplz120_5, $com_obser_faltareq_6, $com_obser_habitualinclusion7, $com_obser_menor16anos_8, $com_obser_invalidez_9, $com_obser_salario_10, $com_obser_pagodomicilio_12, $com_obser_repofond_13;
					$economic_complements = EconomicComplement::whereIn('economic_complements.id', $com_obser_pagodomicilio_12)
						->leftJoin('eco_com_applicants', 'economic_complements.id', '=', 'eco_com_applicants.economic_complement_id')
						->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
						->leftJoin('cities as city_com', 'economic_complements.city_id', '=', 'city_com.id')
						->leftJoin('cities as city_ben', 'eco_com_applicants.city_identity_card_id', '=', 'city_ben.id')
						->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
						->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
						->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
						->distinct('economic_complements.id')
						->select('economic_complements.id as Id', 'economic_complements.code as Nro_tramite', 'eco_com_applicants.first_name as Primer_nombre', 'eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno', 'eco_com_applicants.mothers_last_name as Materno', 'eco_com_applicants.identity_card as CI', 'city_ben.first_shortened as Ext', 'city_com.name as Regional', 'degrees.shortened as Grado', 'eco_com_modalities.shortened as Tipo_renta', 'economic_complements.total as Complemento_Final', 'affiliates.id as affiliate_id')
						->get();

					$rows = array(array('ID', 'Nro de Tramite', 'Nombres y Apellidos', 'C.I.', 'Ext', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Económico Final', 'Observaciones'));
					foreach ($economic_complements as $c) {
                          # code...
						$observaciones = DB::table('affiliate_observations')->where('affiliate_id', $c->affiliate_id)->get();
						$observacion = "";
						foreach ($observaciones as $obs) {
                            # code...
							$observacion = $observacion . " | " . $obs->message;
						}

						array_push($rows, array($c->Id, $c->Nro_tramite, $c->Primer_nombre . ' ' . $c->Segundo_nombre . ' ' . $c->Paterno . ' ' . $c->Materno, $c->CI, $c->Ext, $c->Regional, $c->Grado, $c->Tipo_renta, $c->Complemento_Final, $observacion));
					}

					$sheet->fromArray($rows, null, 'A1', false, false);
					$sheet->cells('A1:J1', function ($cells) {

                            // manipulate the range of cells
						$cells->setBackground('#058A37');
						$cells->setFontColor('#ffffff');

					});

				});

				$excel->sheet('Reposicion de fondo', function ($sheet) {

					global $com_obser_contabilidad_1, $com_obser_prestamos_2, $com_obser_juridica_3, $com_obser_fueraplz90_4, $com_obser_fueraplz120_5, $com_obser_faltareq_6, $com_obser_habitualinclusion7, $com_obser_menor16anos_8, $com_obser_invalidez_9, $com_obser_salario_10, $com_obser_pagodomicilio_12, $com_obser_repofond_13;
					$economic_complements = EconomicComplement::whereIn('economic_complements.id', $com_obser_repofond_13)
						->leftJoin('eco_com_applicants', 'economic_complements.id', '=', 'eco_com_applicants.economic_complement_id')
						->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
						->leftJoin('cities as city_com', 'economic_complements.city_id', '=', 'city_com.id')
						->leftJoin('cities as city_ben', 'eco_com_applicants.city_identity_card_id', '=', 'city_ben.id')
						->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
						->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
						->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
						->distinct('economic_complements.id')
						->select('economic_complements.id as Id', 'economic_complements.code as Nro_tramite', 'eco_com_applicants.first_name as Primer_nombre', 'eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno', 'eco_com_applicants.mothers_last_name as Materno', 'eco_com_applicants.identity_card as CI', 'city_ben.first_shortened as Ext', 'city_com.name as Regional', 'degrees.shortened as Grado', 'eco_com_modalities.shortened as Tipo_renta', 'economic_complements.total as Complemento_Final', 'affiliates.id as affiliate_id')
						->get();

					$rows = array(array('ID', 'Nro de Tramite', 'Nombres y Apellidos', 'C.I.', 'Ext', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Económico Final', 'Observaciones'));
					foreach ($economic_complements as $c) {
                          # code...
						$observaciones = DB::table('affiliate_observations')->where('affiliate_id', $c->affiliate_id)->get();
						$observacion = "";
						foreach ($observaciones as $obs) {
                            # code...
							$observacion = $observacion . " | " . $obs->message;
						}

						array_push($rows, array($c->Id, $c->Nro_tramite, $c->Primer_nombre . ' ' . $c->Segundo_nombre . ' ' . $c->Paterno . ' ' . $c->Materno, $c->CI, $c->Ext, $c->Regional, $c->Grado, $c->Tipo_renta, $c->Complemento_Final, $observacion));
					}

					$sheet->fromArray($rows, null, 'A1', false, false);
					$sheet->cells('A1:J1', function ($cells) { 

                            // manipulate the range of cells
						$cells->setBackground('#058A37');
						$cells->setFontColor('#ffffff');

					});

				});

				$excel->sheet('Apoderados', function ($sheet) {

					global $com_obser_contabilidad_1, $com_obser_prestamos_2, $com_obser_juridica_3, $com_obser_fueraplz90_4, $com_obser_fueraplz120_5, $com_obser_faltareq_6, $com_obser_habitualinclusion7, $com_obser_menor16anos_8, $com_obser_invalidez_9, $com_obser_salario_10, $com_obser_pagodomicilio_12, $com_obser_repofond_13, $com_obser_legalguardian;
					$economic_complements = EconomicComplement::whereIn('economic_complements.id', $com_obser_legalguardian)
						->leftJoin('eco_com_applicants', 'economic_complements.id', '=', 'eco_com_applicants.economic_complement_id')
						->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
						->leftJoin('cities as city_com', 'economic_complements.city_id', '=', 'city_com.id')
						->leftJoin('cities as city_ben', 'eco_com_applicants.city_identity_card_id', '=', 'city_ben.id')
						->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
						->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
						->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
						->distinct('economic_complements.id')
						->select('economic_complements.id as Id', 'economic_complements.code as Nro_tramite', 'eco_com_applicants.first_name as Primer_nombre', 'eco_com_applicants.second_name as Segundo_nombre', 'eco_com_applicants.last_name as Paterno', 'eco_com_applicants.mothers_last_name as Materno', 'eco_com_applicants.identity_card as CI', 'city_ben.first_shortened as Ext', 'city_com.name as Regional', 'degrees.shortened as Grado', 'eco_com_modalities.shortened as Tipo_renta', 'economic_complements.total as Complemento_Final', 'affiliates.id as affiliate_id', 'economic_complements.has_legal_guardian_s')
						->get();

					$rows = array(array('ID', 'Nro de Tramite', 'Nombres y Apellidos', 'C.I.', 'Ext', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Económico Final', 'Tipo Apoderado'));
					foreach ($economic_complements as $c) {
                          # code...
						$apoderado = $c->has_legal_guardian_s ? 'Solicitante' : 'Cobrador';

						array_push($rows, array($c->Id, $c->Nro_tramite, $c->Primer_nombre . ' ' . $c->Segundo_nombre . ' ' . $c->Paterno . ' ' . $c->Materno, $c->CI, $c->Ext, $c->Regional, $c->Grado, $c->Tipo_renta, $c->Complemento_Final, $apoderado));
					}

					$sheet->fromArray($rows, null, 'A1', false, false);
					$sheet->cells('A1:J1', function ($cells) { 

                            // manipulate the range of cells
						$cells->setBackground('#058A37');
						$cells->setFontColor('#ffffff');

					});

				});

			})->download('xls');

        //return $economic_complements;
       // return "contribuciones totales ".$economic_complements->count();
		} else {
			return "funcion no disponible revise su sesion de usuario";
		}
	}

	public function planilla_general_bank()
	{
		ini_set('memory_limit', '-1');
		ini_set('max_execution_time', '-1');
		ini_set('max_input_time', '-1');
		set_time_limit('-1');
		global $rows;
		$afis = DB::table('eco_com_applicants')

			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('cities as cities0', 'economic_complements.city_id', '=', 'cities0.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'eco_com_applicants.city_identity_card_id', '=', 'cities1.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->leftJoin('categories', 'categories.id', '=', 'economic_complements.category_id')
			->leftJoin('cities as cities2', 'affiliates.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('eco_com_procedures', 'economic_complements.eco_com_procedure_id', '=', 'eco_com_procedures.id')
			->whereYear('eco_com_procedures.year', '=', '2018')
			->where('eco_com_procedures.semester', '=', 'Primer')
			->where('economic_complements.workflow_id', '=', 1)
			->where('economic_complements.wf_current_state_id', '=', 3)
			->where('economic_complements.state', 'Edited')
			->where('economic_complements.total', '>', 0)
			->whereRaw('economic_complements.total_rent::numeric < economic_complements.salary_quotable::numeric')
			->whereRaw("not exists(select affiliates.id from affiliate_observations where affiliates.id = 		affiliate_observations.affiliate_id and affiliate_observations.observation_type_id IN(8,9,20,21,24,25) and affiliate_observations.is_enabled = false and affiliate_observations.deleted_at is null) ")
			->whereRaw("not exists(SELECT eco_com_observations.economic_complement_id FROM eco_com_observations
					WHERE economic_complements.id = eco_com_observations.economic_complement_id AND
				  	eco_com_observations.observation_type_id IN (1, 2, 6, 10, 13, 22, 26, 30) AND
				  	eco_com_observations.is_enabled = FALSE AND eco_com_observations.deleted_at is null)")
			->select(DB::raw("economic_complements.id,economic_complements.code,eco_com_applicants.identity_card,cities1.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,eco_com_applicants.birth_date,eco_com_applicants.civil_status,cities0.name as regional,degrees.shortened as degree,eco_com_modalities.shortened as modality,pension_entities.name as gestor,economic_complements.sub_total_rent as renta_boleta,economic_complements.reimbursement as reintegro,economic_complements.dignity_pension,economic_complements.total_rent as renta_neta,economic_complements.total_rent_calc as neto,categories.name as category,economic_complements.salary_reference,economic_complements.seniority as antiguedad,economic_complements.salary_quotable,economic_complements.difference,economic_complements.total_amount_semester,economic_complements.complementary_factor,economic_complements.total,reception_type as tipo_tramite,affiliates.identity_card as ci_afiliado, cities2.first_shortened as ext_afiliado,affiliates.first_name as pn_afiliado,affiliates.second_name as sn_afiliado,affiliates.last_name as ap_afiliado,affiliates.mothers_last_name as am_afiliado,affiliates.surname_husband as ap_casado_afiliado,eco_com_modalities.id as modality_id, economic_complements.amount_loan , economic_complements.amount_replacement, economic_complements.amount_accounting, economic_complements.amount_credit"))

			->get();
		$rows = array(array('Nro', 'Nro Tramite', 'C.I.', 'Ext', 'Primer Nombre', 'Segundo Nombre', 'Apellido Paterno', 'Apellido Materno', 'Apellido de Casado', 'Ci Causahabiente', 'Ext', 'Primer Nombre Causahabiente', 'Segundo Nombre Causahabiente', 'Apellido Paterno Causahabiente', ' Apellido Materno Causahabiente', 'Apellido Casado Causahabiente', 'Fecha de Nacimiento', 'Estado Civil', 'Regional', 'Grado', 'Tipo de Renta', 'Ente Gestor', 'Renta Boleta', 'Reintegro', 'Renta Dignidad', 'Renta Total Neta', 'Neto', 'Categoria', 'Referente Salarial', 'Antiguedad', 'Cotizable', 'Diferencia', 'Total Semestre', 'Factor de Complementacion', 'Complemento Economico final', 'Amortizacion', 'Complemento sin Amortizacion', 'Tipo de tramite'));
		$i = 1;
		foreach ($afis as $a) {
			switch ($a->modality_id) {
				case '1':
				case '4':
				case '6':
				case '8':
					$afiliado_ci = "";
					$afiliado_ext = "";
					$afiliado_first_name = "";
					$afiliado_second_name = "";
					$afiliado_last_nme = "";
					$afiliado_mother_last_name = "";
					$afiliado_surname_husband = "";
					break;
				default:
					$afiliado_ci = $a->ci_afiliado;
					$afiliado_ext = $a->ext_afiliado;
					$afiliado_first_name = $a->pn_afiliado;
					$afiliado_second_name = $a->sn_afiliado;
					$afiliado_last_nme = $a->ap_afiliado;
					$afiliado_mother_last_name = $a->am_afiliado;
					$afiliado_surname_husband = $a->ap_casado_afiliado;
					break;
			}
			$amortization = str_replace(',', '', ($a->amount_loan ?? 0.0 + $a->amount_replacement ?? 0.0 + $a->amount_accounting ?? 0.0 + $a->amount_credit ?? 0.0));
			if ($amortization == 0) {
				$amortization = null;
			}
			$total_temp = str_replace(',', '', ($amortization + $a->total));
			array_push($rows, array($i, $a->code, $a->identity_card, $a->ext, $a->first_name, $a->second_name, $a->last_name, $a->mothers_last_name, $a->surname_husband, $afiliado_ci, $afiliado_ext, $afiliado_first_name, $afiliado_second_name, $afiliado_last_nme, $afiliado_mother_last_name, $afiliado_surname_husband, $a->birth_date, $a->civil_status, $a->regional, $a->degree, $a->modality, $a->gestor, $a->renta_boleta, $a->reintegro, $a->dignity_pension, $a->renta_neta, $a->neto, $a->category, $a->salary_reference, $a->antiguedad, $a->salary_quotable, $a->difference, $a->total_amount_semester, $a->complementary_factor, $a->total, $amortization, $total_temp, $a->tipo_tramite));
			$i++;
		}

		Excel::create('Planilla General Banco' . date("Y-m-d H:i:s"), function ($excel) {

			global $rows;
			$excel->sheet('Planilla General Banco', function ($sheet) {

				global $rows;

				$sheet->fromArray($rows, null, 'A1', false, false);
				$sheet->cells('A1:AL1', function ($cells) {

                          // manipulate the range of cells
					$cells->setBackground('#058A37');
					$cells->setFontColor('#ffffff');
					$cells->setFontWeight('bold');

				});
			});

		})->download('xls');

          // dd($rows);

	}
	public function planilla_general()
	{
		global $rows;
		ini_set('memory_limit', '-1');
		ini_set('max_execution_time', '-1');
		ini_set('max_input_time', '-1');
		set_time_limit('-1');
		$afis = DB::table('eco_com_applicants')
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('cities as cities0', 'economic_complements.city_id', '=', 'cities0.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'eco_com_applicants.city_identity_card_id', '=', 'cities1.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->leftJoin('categories', 'categories.id', '=', 'economic_complements.category_id')
			->leftJoin('cities as cities2', 'affiliates.city_identity_card_id', '=', 'cities2.id')
			->whereYear('economic_complements.year', '=', '2017')
			->where('economic_complements.semester', '=', 'Primer')
			->where('economic_complements.workflow_id', '=', 1)
			->where('economic_complements.wf_current_state_id', 2)
			->where('economic_complements.state', 'Edited')
			->whereNotNull('economic_complements.review_date')
			->select(DB::raw("economic_complements.id,economic_complements.code,eco_com_applicants.identity_card,cities1.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,eco_com_applicants.birth_date,eco_com_applicants.civil_status,cities0.name as regional,degrees.shortened as degree,eco_com_modalities.shortened as modality,pension_entities.name as gestor,economic_complements.sub_total_rent as renta_boleta,economic_complements.reimbursement as reintegro,economic_complements.dignity_pension,economic_complements.total_rent as renta_neta,economic_complements.total_rent_calc as neto,categories.name as category,economic_complements.salary_reference,economic_complements.seniority as antiguedad,economic_complements.salary_quotable,economic_complements.difference,economic_complements.total_amount_semester,economic_complements.complementary_factor,economic_complements.total,reception_type as tipo_tramite,affiliates.identity_card as ci_afiliado, cities2.first_shortened as ext_afiliado,affiliates.first_name as pn_afiliado,affiliates.second_name as sn_afiliado,affiliates.last_name as ap_afiliado,affiliates.mothers_last_name as am_afiliado,affiliates.surname_husband as ap_casado_afiliado,eco_com_modalities.id as modality_id, economic_complements.amount_loan , economic_complements.amount_replacement, economic_complements.amount_accounting, economic_complements.amount_credit"))
			->get();
		$rows = array(array('Nro', 'Nro Tramite', 'C.I.', 'Ext', 'Primer Nombre', 'Segundo Nombre', 'Apellido Paterno', 'Apellido Materno', 'Apellido de Casado', 'Ci Causahabiente', 'Ext', 'Primer Nombre Causahabiente', 'Segundo Nombre Causahabiente', 'Apellido Paterno Causahabiente', ' Apellido Materno Causahabiente', 'Apellido Casado Causahabiente', 'Fecha de Nacimiento', 'Estado Civil', 'Regional', 'Grado', 'Tipo de Renta', 'Ente Gestor', 'Renta Boleta', 'Reintegro', 'Renta Dignidad', 'Renta Total Neta', 'Neto', 'Categoria', 'Referente Salarial', 'Antiguedad', 'Cotizable', 'Diferencia', 'Total Semestre', 'Factor de Complementacion', 'Complemento Economico final', 'Amortizacion', 'Complemento sin Amortizacion', 'Tipo de tramite'));

		$i = 1;
		foreach ($afis as $a) {
			switch ($a->modality_id) {
				case '1':
				case '4':
				case '6':
				case '8':
					$afiliado_ci = "";
					$afiliado_ext = "";
					$afiliado_first_name = "";
					$afiliado_second_name = "";
					$afiliado_last_nme = "";
					$afiliado_mother_last_name = "";
					$afiliado_surname_husband = "";
					break;
				default:
					$afiliado_ci = $a->ci_afiliado;
					$afiliado_ext = $a->ext_afiliado;
					$afiliado_first_name = $a->pn_afiliado;
					$afiliado_second_name = $a->sn_afiliado;
					$afiliado_last_nme = $a->ap_afiliado;
					$afiliado_mother_last_name = $a->am_afiliado;
					$afiliado_surname_husband = $a->ap_casado_afiliado;
					break;
			}
			$amortization = str_replace(',', '', ($a->amount_loan ?? 0 + $a->amount_replacement ?? 0 + $a->amount_accounting ?? 0 + $a->amount_credit ?? 0));
			if ($amortization == 0) {
				$amortization = null;
			}
			$total_temp = str_replace(',', '', ($amortization + $a->total));
			array_push($rows, array($i, $a->code, $a->identity_card, $a->ext, $a->first_name, $a->second_name, $a->last_name, $a->mothers_last_name, $a->surname_husband, $afiliado_ci, $afiliado_ext, $afiliado_first_name, $afiliado_second_name, $afiliado_last_nme, $afiliado_mother_last_name, $afiliado_surname_husband, $a->birth_date, $a->civil_status, $a->regional, $a->degree, $a->modality, $a->gestor, $a->renta_boleta, $a->reintegro, $a->dignity_pension, $a->renta_neta, $a->neto, $a->category, $a->salary_reference, $a->antiguedad, $a->salary_quotable, $a->difference, $a->total_amount_semester, $a->complementary_factor, $a->total, $amortization, $total_temp, $a->tipo_tramite));
			$i++;
		}
		Excel::create('Planilla General Revizados ' . date("Y-m-d H:i:s"), function ($excel) {
			global $rows;
			$excel->sheet('Planilla General Revizados', function ($sheet) {
				global $rows;
				$sheet->fromArray($rows, null, 'A1', false, false);
				$sheet->cells('A1:AL1', function ($cells) {
					$cells->setBackground('#058A37');
					$cells->setFontColor('#ffffff');
					$cells->setFontWeight('bold');
				});
			});
		})->download('xls');
	}


    //########## EXPORT PLANILLA BY DEPARTMENT
	public function export_by_department_bank(Request $request)
	{
		ini_set('memory_limit', '-1');
		ini_set('max_execution_time', '-1');
		ini_set('max_input_time', '-1');
		set_time_limit('-1');
		global $list, $ben, $suc, $cbb, $lpz, $oru, $pdo, $pts, $scz, $tja;
		if (is_null($request->year) || is_null($request->semester)) {

			Session::flash('message', "Seleccione Año y Semestre");
			return redirect('economic_complement');
		} else {
			$list = DB::table('eco_com_applicants')
				->select(DB::raw("economic_complements.id,economic_complements.code,eco_com_applicants.identity_card as app_ci,cities1.first_shortened as app_ext,eco_com_applicants.first_name, eco_com_applicants.second_name, eco_com_applicants.last_name, eco_com_applicants.mothers_last_name, eco_com_applicants.surname_husband,
                                            affiliates.identity_card as afi_ci,cities2.first_shortened as afi_ext,affiliates.first_name as afi_first_name, affiliates.second_name as afi_second_name, affiliates.last_name as afi_last_name, affiliates.mothers_last_name as afi_mothers_last_name, 
                                            affiliates.surname_husband as afi_surname_husband,eco_com_applicants.birth_date,eco_com_applicants.civil_status,cities0.second_shortened as regional,degrees.shortened as degree,eco_com_modalities.shortened as modality,pension_entities.name as entity,economic_complements.sub_total_rent,economic_complements.reimbursement,economic_complements.dignity_pension,economic_complements.total_rent,economic_complements.total_rent_calc,categories.name as category,economic_complements.salary_reference,economic_complements.seniority,economic_complements.salary_quotable,economic_complements.difference,economic_complements.total_amount_semester,economic_complements.complementary_factor,economic_complements.total,economic_complements.reception_type"))
				->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
				->leftJoin('cities as cities0', 'economic_complements.city_id', '=', 'cities0.id')
				->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
				->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
				->leftJoin('cities as cities1', 'eco_com_applicants.city_identity_card_id', '=', 'cities1.id')
				->leftJoin('eco_com_types', 'eco_com_modalities.eco_com_type_id', '=', 'eco_com_types.id')
				->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
				->leftJoin('cities as cities2', 'affiliates.city_identity_card_id', '=', 'cities2.id')
				->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
				->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
				->whereYear('economic_complements.year', '=', $request->year)
				->where('economic_complements.semester', '=', $request->semester)
				->where('economic_complements.workflow_id', '=', 1)
				->where('economic_complements.wf_current_state_id', 3)
				->where('economic_complements.state', 'Edited')
				->where('economic_complements.total', '>', 0)
				->whereRaw('economic_complements.total_rent::numeric < economic_complements.salary_quotable::numeric')
				->whereRaw("not exists(select affiliates.id from affiliate_observations where affiliates.id = affiliate_observations.affiliate_id and affiliate_observations.observation_type_id IN(1,2,3,12,13,14,15) and affiliate_observations.is_enabled = false and affiliate_observations.deleted_at is null)")         
                                          //->whereNotNull('economic_complements.review_date')                                    
				->orderBy('cities0.second_shortened', 'ASC')->get();

			$encb = array('NRO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'APELLIDO_PATERNO', 'APELLIDO_MATERNO', 'APELLIDO_DE_CASADO', 'CI_CAUSAHABIENTE', 'EXT', 'PRIMER_NOMBRE_CAUSAHABIENTE', 'SEGUNDO_NOMBRE_CAUSAHABIENTE', 'APELLIDO_PATERNO_CAUSAHABIENTE', 'APELLIDO_MATERNO_CAUSAHABIENTE', 'APELLIDO_DE_CASADO_CAUSAHABIENTE', 'FECHA_NACIMIENTO', 'ESTADO_CIVIL', 'REGIONAL', 'GRADO', 'TIPO_DE_RENTA', 'ENTE_GESTOR', 'RENTA_BOLETA', 'REINTEGRO', 'RENTA_DIGNIDAD', 'RENTA_TOTAL_NETA', 'NETO', 'CATEGORIA', 'REFERENTE_SALARIAL', 'ANTIGUEDAD', 'COTIZABLE', 'DIFERENCIA', 'TOTAL_SEMESTRE', 'FACTOR_DE_COMPLEMENTACION', 'COMPLEMENTO_ECONOMICO_FINAL_2017', 'AMORTIZACION', 'COMPLEMENTO SIN AMORTIZACION', 'TIPO_TRAMITE');
			$ben[] = $encb;
			$suc[] = $encb;
			$cbb[] = $encb;
			$lpz[] = $encb;
			$oru[] = $encb;
			$pdo[] = $encb;
			$pts[] = $encb;
			$scz[] = $encb;
			$tja[] = $encb;
			foreach ($list as $datos) {
				$economic = EconomicComplement::idIs($datos->id)->first();                    
                    //$import = $datos->importe;
				$amortization = str_replace(',', '', ($economic->amount_loan ?? 0 + $economic->amount_replacement ?? 0 + $economic->amount_accounting ?? 0 + $economic->amount_credit ?? 0));
				if ($amortization == 0) {
					$amortization = null;
				}
				$total_temp = str_replace(',', '', ($amortization + $datos->total));
				if ($economic->has_legal_guardian) {
					$legal1 = EconomicComplementLegalGuardian::where('economic_complement_id', '=', $economic->id)->first();
					$obj = array($datos->code, $datos->app_ci, $datos->app_ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->afi_ci, $datos->afi_ext, $datos->afi_first_name, $datos->afi_second_name, $datos->afi_last_name, $datos->afi_mothers_last_name, $datos->afi_surname_husband, $datos->birth_date, $datos->civil_status, $datos->regional, $datos->degree, $datos->modality, $datos->entity, $datos->sub_total_rent, $datos->reimbursement, $datos->dignity_pension, $datos->total_rent, $datos->total_rent_calc, $datos->category, $datos->salary_reference, $datos->seniority, $datos->salary_quotable, $datos->difference, $datos->total_amount_semester, $datos->complementary_factor, $datos->total, $amortization, $total_temp, $datos->reception_type);

				} else {
					$apl = EconomicComplement::find($datos->id)->economic_complement_applicant;
					$obj = array($datos->code, $datos->app_ci, $datos->app_ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->afi_ci, $datos->afi_ext, $datos->afi_first_name, $datos->afi_second_name, $datos->afi_last_name, $datos->afi_mothers_last_name, $datos->afi_surname_husband, $datos->birth_date, $datos->civil_status, $datos->regional, $datos->degree, $datos->modality, $datos->entity, $datos->sub_total_rent, $datos->reimbursement, $datos->dignity_pension, $datos->total_rent, $datos->total_rent_calc, $datos->category, $datos->salary_reference, $datos->seniority, $datos->salary_quotable, $datos->difference, $datos->total_amount_semester, $datos->complementary_factor, $datos->total, $amortization, $total_temp, $datos->reception_type);

				}

				switch ($datos->regional) {
					case "BEN":
						$ben[] = $obj;
						break;
					case "SUC":
						$suc[] = $obj;
						break;
					case "CBB":
						$cbb[] = $obj;
						break;
					case "LPZ":
						$lpz[] = $obj;
						break;
					case "ORU":
						$oru[] = $obj;
						break;
					case "PDO":
						$pdo[] = $obj;
						break;
					case "PTS":
						$pts[] = $obj;
						break;
					case "SCZ":
						$scz[] = $obj;
						break;
					case "TJA":
						$tja[] = $obj;
						break;
				}
			}

			global $ben, $suc, $cbb, $lpz, $oru, $pdo, $pts, $scz, $tja;
			Excel::create('PLANILLA_POR_DEPARTAMENTO', function ($excel) {
				global $ben, $suc, $cbb, $lpz, $oru, $pdo, $pts, $scz, $tja;
				$excel->sheet('BENI', function ($sheet) use ($ben) {
					$sheet->fromArray($ben, null, 'A1', false, false);
				});

				$excel->sheet('CHUQUISACA', function ($sheet) use ($suc) {
					$sheet->fromArray($suc, null, 'A1', false, false);
				});

				$excel->sheet('COCHABAMBA', function ($sheet) use ($cbb) {
					$sheet->fromArray($cbb, null, 'A1', false, false);
				});

				$excel->sheet('LA PAZ', function ($sheet) use ($lpz) {
					$sheet->fromArray($lpz, null, 'A1', false, false);
				});

				$excel->sheet('ORURO', function ($sheet) use ($oru) {
					$sheet->fromArray($oru, null, 'A1', false, false);
				});

				$excel->sheet('PANDO', function ($sheet) use ($pdo) {
					$sheet->fromArray($pdo, null, 'A1', false, false);
				});

				$excel->sheet('POTOSI', function ($sheet) use ($pts) {
					$sheet->fromArray($pts, null, 'A1', false, false);
				});

				$excel->sheet('SANTA CRUZ', function ($sheet) use ($scz) {
					$sheet->fromArray($scz, null, 'A1', false, false);
				});

				$excel->sheet('TARIJA', function ($sheet) use ($tja) {
					$sheet->fromArray($tja, null, 'A1', false, false);
				});

			})->export('xlsx');
		}
	}



	public function export_by_department(Request $request)
	{
		ini_set('memory_limit', '-1');
		ini_set('max_execution_time', '-1');
		ini_set('max_input_time', '-1');
		set_time_limit('-1');
		global $list, $ben, $suc, $cbb, $lpz, $oru, $pdo, $pts, $scz, $tja;

		if (is_null($request->year) || is_null($request->semester)) {

			Session::flash('message', "Seleccione Año y Semestre");
			return redirect('economic_complement');
		} else {
			$list = DB::table('eco_com_applicants')
				->select(DB::raw("economic_complements.id,economic_complements.code,eco_com_applicants.identity_card as app_ci,cities1.first_shortened as app_ext,eco_com_applicants.first_name, eco_com_applicants.second_name, eco_com_applicants.last_name, eco_com_applicants.mothers_last_name, eco_com_applicants.surname_husband,
                                            affiliates.identity_card as afi_ci,cities2.first_shortened as afi_ext,affiliates.first_name as afi_first_name, affiliates.second_name as afi_second_name, affiliates.last_name as afi_last_name, affiliates.mothers_last_name as afi_mothers_last_name, 
                                            affiliates.surname_husband as afi_surname_husband,eco_com_applicants.birth_date,eco_com_applicants.civil_status,cities0.second_shortened as regional,degrees.shortened as degree,eco_com_modalities.shortened as modality,pension_entities.name as entity,economic_complements.sub_total_rent,economic_complements.reimbursement,economic_complements.dignity_pension,economic_complements.total_rent,economic_complements.total_rent_calc,categories.name as category,economic_complements.salary_reference,economic_complements.seniority,economic_complements.salary_quotable,economic_complements.difference,economic_complements.total_amount_semester,economic_complements.complementary_factor,economic_complements.total,economic_complements.reception_type"))
				->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
				->leftJoin('cities as cities0', 'economic_complements.city_id', '=', 'cities0.id')
				->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
				->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
				->leftJoin('cities as cities1', 'eco_com_applicants.city_identity_card_id', '=', 'cities1.id')
				->leftJoin('eco_com_types', 'eco_com_modalities.eco_com_type_id', '=', 'eco_com_types.id')
				->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
				->leftJoin('cities as cities2', 'affiliates.city_identity_card_id', '=', 'cities2.id')
				->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
				->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
				->where('economic_complements.eco_com_procedure_id', '=', 2)
				->where('economic_complements.workflow_id', '=', 1)
				->where('economic_complements.wf_current_state_id', '=', 2)
				->where('economic_complements.state', '=', 'Edited')
				->whereNotNull('economic_complements.review_date')
				->orderBy('cities0.second_shortened', 'ASC')->get();

			$encb = array('NRO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'APELLIDO_PATERNO', 'APELLIDO_MATERNO', 'APELLIDO_DE_CASADO', 'CI_CAUSAHABIENTE', 'EXT', 'PRIMER_NOMBRE_CAUSAHABIENTE', 'SEGUNDO_NOMBRE_CAUSAHABIENTE', 'APELLIDO_PATERNO_CAUSAHABIENTE', 'APELLIDO_MATERNO_CAUSAHABIENTE', 'APELLIDO_DE_CASADO_CAUSAHABIENTE', 'FECHA_NACIMIENTO', 'ESTADO_CIVIL', 'REGIONAL', 'GRADO', 'TIPO_DE_RENTA', 'ENTE_GESTOR', 'RENTA_BOLETA', 'REINTEGRO', 'RENTA_DIGNIDAD', 'RENTA_TOTAL_NETA', 'NETO', 'CATEGORIA', 'REFERENTE_SALARIAL', 'ANTIGUEDAD', 'COTIZABLE', 'DIFERENCIA', 'TOTAL_SEMESTRE', 'FACTOR_DE_COMPLEMENTACION', 'COMPLEMENTO_ECONOMICO_FINAL_2017', 'AMORTIZACION', 'COMPLEMENTO SIN AMORTIZACION', 'TIPO_TRAMITE');
			$ben[] = $encb;
			$suc[] = $encb;
			$cbb[] = $encb;
			$lpz[] = $encb;
			$oru[] = $encb;
			$pdo[] = $encb;
			$pts[] = $encb;
			$scz[] = $encb;
			$tja[] = $encb;
			foreach ($list as $datos) {
				$economic = EconomicComplement::idIs($datos->id)->first();
				$amortization = str_replace(',', '', ($economic->amount_loan ?? 0 + $economic->amount_replacement ?? 0 + $economic->amount_accounting ?? 0 + $economic->amount_credit ?? 0));
				if ($amortization == 0) {
					$amortization = null;
				}
				$total_temp = str_replace(',', '', ($amortization + $datos->total));
                    //$import = $datos->importe;
				if ($economic->has_legal_guardian) {
					$legal1 = EconomicComplementLegalGuardian::where('economic_complement_id', '=', $economic->id)->first();
					$obj = array($datos->code, $datos->app_ci, $datos->app_ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->afi_ci, $datos->afi_ext, $datos->afi_first_name, $datos->afi_second_name, $datos->afi_last_name, $datos->afi_mothers_last_name, $datos->afi_surname_husband, $datos->birth_date, $datos->civil_status, $datos->regional, $datos->degree, $datos->modality, $datos->entity, $datos->sub_total_rent, $datos->reimbursement, $datos->dignity_pension, $datos->total_rent, $datos->total_rent_calc, $datos->category, $datos->salary_reference, $datos->seniority, $datos->salary_quotable, $datos->difference, $datos->total_amount_semester, $datos->complementary_factor, $datos->total, $amortization, $total_temp, $datos->reception_type);

				} else {
					$apl = EconomicComplement::find($datos->id)->economic_complement_applicant;
					$obj = array($datos->code, $datos->app_ci, $datos->app_ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->afi_ci, $datos->afi_ext, $datos->afi_first_name, $datos->afi_second_name, $datos->afi_last_name, $datos->afi_mothers_last_name, $datos->afi_surname_husband, $datos->birth_date, $datos->civil_status, $datos->regional, $datos->degree, $datos->modality, $datos->entity, $datos->sub_total_rent, $datos->reimbursement, $datos->dignity_pension, $datos->total_rent, $datos->total_rent_calc, $datos->category, $datos->salary_reference, $datos->seniority, $datos->salary_quotable, $datos->difference, $datos->total_amount_semester, $datos->complementary_factor, $datos->total, $amortization, $total_temp, $datos->reception_type);

				}

				switch ($datos->regional) {
					case "BEN":
						$ben[] = $obj;
						break;
					case "SUC":
						$suc[] = $obj;
						break;
					case "CBB":
						$cbb[] = $obj;
						break;
					case "LPZ":
						$lpz[] = $obj;
						break;
					case "ORU":
						$oru[] = $obj;
						break;
					case "PDO":
						$pdo[] = $obj;
						break;
					case "PTS":
						$pts[] = $obj;
						break;
					case "SCZ":
						$scz[] = $obj;
						break;
					case "TJA":
						$tja[] = $obj;
						break;
				}
			}

			global $ben, $suc, $cbb, $lpz, $oru, $pdo, $pts, $scz, $tja;
			Excel::create('PLANILLA_POR_DEPARTAMENTO', function ($excel) {
				global $ben, $suc, $cbb, $lpz, $oru, $pdo, $pts, $scz, $tja;
				$excel->sheet('BENI', function ($sheet) use ($ben) {
					$sheet->fromArray($ben, null, 'A1', false, false);
				});

				$excel->sheet('CHUQUISACA', function ($sheet) use ($suc) {
					$sheet->fromArray($suc, null, 'A1', false, false);
				});

				$excel->sheet('COCHABAMBA', function ($sheet) use ($cbb) {
					$sheet->fromArray($cbb, null, 'A1', false, false);
				});

				$excel->sheet('LA PAZ', function ($sheet) use ($lpz) {
					$sheet->fromArray($lpz, null, 'A1', false, false);
				});

				$excel->sheet('ORURO', function ($sheet) use ($oru) {
					$sheet->fromArray($oru, null, 'A1', false, false);
				});

				$excel->sheet('PANDO', function ($sheet) use ($pdo) {
					$sheet->fromArray($pdo, null, 'A1', false, false);
				});

				$excel->sheet('POTOSI', function ($sheet) use ($pts) {
					$sheet->fromArray($pts, null, 'A1', false, false);
				});

				$excel->sheet('SANTA CRUZ', function ($sheet) use ($scz) {
					$sheet->fromArray($scz, null, 'A1', false, false);
				});

				$excel->sheet('TARIJA', function ($sheet) use ($tja) {
					$sheet->fromArray($tja, null, 'A1', false, false);
				});

			})->export('xlsx');
		}
	}

	public function payrollLegalGuardian()
	{
		global $rows, $i;
		$eco = EconomicComplement::where('eco_com_procedure_id', '=', 6)
            //->whereNotNull('review_date')
			->where('wf_current_state_id', '=', '3')
			->where('state', 'like', 'Edited')
			->where('has_legal_guardian', '=', true)
			->where('has_legal_guardian_s', '=', false)
			->get();
		$rows[] = array('Nro', 'C.I.', 'Nombre Completo Poderdante', 'C.I.', 'Nombre Completo Apoderado', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Economico');
		$i = 1;
		foreach ($eco as $e) {
			$app = $e->economic_complement_applicant;
			$apo = $e->economic_complement_legal_guardian;
			$data = new stdClass;
			$data->index = $i++;
			$data->ci_app = $app->identity_card . ' ' . $app->city_identity_card->first_shortened;
			$data->name_app = $app->getFullName();
			$data->ci_apo = $apo->identity_card . ' ' . $apo->city_identity_card->first_shortened;
			$data->name = $apo->getFullName();
			$data->city = $e->city->name;
			$data->degree = $e->degree->shortened;
			$data->eco_com_type = strtoupper($e->economic_complement_modality->economic_complement_type->name);
			$data->total = $e->total;
            // $rows[] = get_object_vars($data);
			$rows[] = (array)($data);
		}
		Excel::create('Planilla de casos de Apoderados y poderdantes Revizados', function ($excel) {
			global $rows;
			$excel->sheet('Apoderados Revizados', function ($sheet) {
				global $rows;
				$sheet->fromArray($rows, null, 'A1', false, false);
				$sheet->cells('A1:I1', function ($cells) {
					$cells->setBackground('#058A37');
					$cells->setFontColor('#ffffff');
					$cells->setFontWeight('bold');
				});
				$sheet->setAllBorders('thin');
			});
		})->download('xls');
	}
	public function payrollHome()
	{
		global $rows, $i;
		$aff = DB::table('affiliates')
			->leftJoin('affiliate_observations', 'affiliates.id', '=', 'affiliate_observations.affiliate_id')
			->leftJoin('observation_types', 'affiliate_observations.observation_type_id', '=', 'observation_types.id')
			->where('observation_types.id', '=', 12)
			->get();
		$rows[] = array('Nro', 'C.I.', 'Nombre Completo', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Economico');
		$i = 1;
		$total = 0;
		foreach ($aff as $a) {
			if ($e = Affiliate::find($a->affiliate_id)->economic_complements()->where('eco_com_procedure_id', '=', 2)->where('state', 'like', 'Edited')->whereNotNull('review_date')->first()) {
				$app = $e->economic_complement_applicant;
				$data = new stdClass;
				$data->index = $i++;
				$data->ci_app = $app->identity_card . ' ' . $app->city_identity_card->first_shortened;
				$data->name_app = $app->getFullName();
				$data->city = $e->city->name;
				$data->degree = $e->degree->shortened;
				$data->eco_com_type = strtoupper($e->economic_complement_modality->economic_complement_type->name);
				$data->total = $e->total;
				$total += $e->total;
				$rows[] = (array)($data);
			}
		}
		Excel::create('Planilla de pago a domicilio', function ($excel) {
			global $rows, $i;
			$excel->sheet('Pago a Domicilio', function ($sheet) {
				global $rows, $i;
				++$i;
				$sheet->fromArray($rows, null, 'A1', false, false);
				$sheet->cells('A1:G1', function ($cells) {
					$cells->setBackground('#058A37');
					$cells->setFontColor('#ffffff');
					$cells->setFontWeight('bold');
				});
			});
		})->download('xls');
	}
	public function payrollReplenishmentFunds()
	{
		global $rows, $i;
		$aff = DB::table('affiliates')
			->leftJoin('affiliate_observations', 'affiliates.id', '=', 'affiliate_observations.affiliate_id')
			->leftJoin('observation_types', 'affiliate_observations.observation_type_id', '=', 'observation_types.id')
			->where('observation_types.id', '=', 13)
			->get();
		$rows[] = array('Nro', 'C.I.', 'Nombre Completo', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Economico', 'Reposición', 'Complemento Economico sin Reposicion');
		$i = 1;
		$total = 0;
		foreach ($aff as $a) {
			if ($e = Affiliate::find($a->affiliate_id)->economic_complements()->where('eco_com_procedure_id', '=', 2)->where('state', 'like', 'Edited')->whereNotNull('review_date')->first()) {
				$app = $e->economic_complement_applicant;
				$data = new stdClass;
				$data->index = $i++;
				$data->ci_app = $app->identity_card . ' ' . $app->city_identity_card->first_shortened;
				$data->name_app = $app->getFullName();
				$data->city = $e->city->name;
				$data->degree = $e->degree->shortened;
				$data->eco_com_type = strtoupper($e->economic_complement_modality->economic_complement_type->name);
				$data->total = $e->total;
				$replacement = $e->amount_replacement;
				$data->replacement = $replacement;
				$data->total_temp = str_replace(',', '', ($e->total + $replacement));
				$total += $e->total;
				$rows[] = (array)($data);
			}
		}
		Excel::create('Planilla de reposición de fondos', function ($excel) {
			global $rows, $i;
			$excel->sheet('Reposición De Fondos', function ($sheet) {
				global $rows, $i;
				++$i;
				$sheet->fromArray($rows, null, 'A1', false, false);
				$sheet->cells('A1:I1', function ($cells) {
					$cells->setBackground('#058A37');
					$cells->setFontColor('#ffffff');
					$cells->setFontWeight('bold');
				});
			});
		})->download('xls');
	}
	public function payrollLoan()
	{
		global $rows, $i;
		$aff = DB::table('affiliates')
			->leftJoin('affiliate_observations', 'affiliates.id', '=', 'affiliate_observations.affiliate_id')
			->leftJoin('observation_types', 'affiliate_observations.observation_type_id', '=', 'observation_types.id')
			->where('observation_types.id', '=', 2)
			->get();
		$rows[] = array('Nro', 'C.I.', 'Nombre Completo', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Economico', 'Amortizacion', 'Complemento Economico sin Amortizacion');
		$i = 1;
		$total = 0;
		foreach ($aff as $a) {
			if ($e = Affiliate::find($a->affiliate_id)->economic_complements()->where('eco_com_procedure_id', '=', 2)->where('state', 'like', 'Edited')->whereNotNull('review_date')->first()) {
				$app = $e->economic_complement_applicant;
				$data = new stdClass;
				$data->index = $i++;
				$data->ci_app = $app->identity_card . ' ' . $app->city_identity_card->first_shortened;
				$data->name_app = $app->getFullName();
				$data->city = $e->city->name;
				$data->degree = $e->degree->shortened;
				$data->eco_com_type = strtoupper($e->economic_complement_modality->economic_complement_type->name);
				$data->total = $e->total;
				$data->loan = $e->amount_loan;
				$data->total_temp = str_replace(',', '', ($e->total + $e->amount_loan));
				$total += $e->total;
				$rows[] = (array)($data);
			}
		}
		Excel::create('Planilla de observados por situación de mora por prestamos', function ($excel) {
			global $rows, $i;
			$excel->sheet('Situación de mora por prestamos', function ($sheet) {
				global $rows, $i;
				++$i;
				$sheet->fromArray($rows, null, 'A1', false, false);
				$sheet->cells('A1:I1', function ($cells) {
					$cells->setBackground('#058A37');
					$cells->setFontColor('#ffffff');
					$cells->setFontWeight('bold');
				});
			});
		})->download('xls');
	}
	public function payrollaccounting()
	{
		global $rows, $i;
		$aff = DB::table('affiliates')
			->leftJoin('affiliate_observations', 'affiliates.id', '=', 'affiliate_observations.affiliate_id')
			->leftJoin('observation_types', 'affiliate_observations.observation_type_id', '=', 'observation_types.id')
			->where('observation_types.id', '=', 1)
			->get();
		$rows[] = array('Nro', 'C.I.', 'Nombre Completo', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Economico');
		$i = 1;
		$total = 0;
		foreach ($aff as $a) {
			if ($e = Affiliate::find($a->affiliate_id)->economic_complements()->where('eco_com_procedure_id', '=', 2)->where('state', 'like', 'Edited')->whereNotNull('review_date')->first()) {
				$app = $e->economic_complement_applicant;
				$data = new stdClass;
				$data->index = $i++;
				$data->ci_app = $app->identity_card . ' ' . $app->city_identity_card->first_shortened;
				$data->name_app = $app->getFullName();
				$data->city = $e->city->name;
				$data->degree = $e->degree->shortened;
				$data->eco_com_type = strtoupper($e->economic_complement_modality->economic_complement_type->name);
				$data->total = $e->total;
				$total += $e->total;
				$rows[] = (array)($data);
			}
		}
		Excel::create('Planilla de observados por cuentas por cobrar', function ($excel) {
			global $rows, $i;
			$excel->sheet('Cuentas por Cobrar', function ($sheet) {
				global $rows, $i;
				++$i;
				$sheet->fromArray($rows, null, 'A1', false, false);
				$sheet->cells('A1:G1', function ($cells) {
					$cells->setBackground('#058A37');
					$cells->setFontColor('#ffffff');
					$cells->setFontWeight('bold');
				});
			});
		})->download('xls');
	}

	public function export_not_review()
	{
		if (Auth::check()) {
			ini_set('memory_limit', '-1');
			ini_set('max_execution_time', '-1');
			ini_set('max_input_time', '-1');
			set_time_limit('-1');
			global $rows;

			$complementos = EconomicComplement::where('economic_complements.workflow_id', '<=', '3')
                                            //->where('economic_complements.wf_current_state_id','2')
				->where('economic_complements.state', 'Received')
				->where('economic_complements.eco_com_procedure_id', '6')
				->whereExists(function ($query) {
					$query->from('affiliate_observations')
						->whereRaw('economic_complements.affiliate_id = affiliate_observations.affiliate_id and affiliate_observations.deleted_at is null');
				})
				->get();
			Log::info("Cantidad de complementos: " . $complementos->count());

			$rows = array();
			array_push($rows, array(
				"ID",
				"Numero de Tramite",
				"Fecha de Recepcion",
				"Beneficiario CI",
				"Beneficiario Ext",
				"Beneficiario Primer Nombre",
				"Beneficiario Segundo Nombre",
				"Beneficiario Apellido Paterno",
				"Beneficiario Apellido Materno",
				"Beneficiario Apellido Conyugue",
				"Regional",
				"Tipo de Tramite",
				"Categoria",
				"Sueldo Base ",
				"Grado",
				"Ente Gestor",
				"Genero",
				"SRenta Boleta",
				"Renta Dignidad",
				"Renta Neto",
				"Neto",
				"Salario Referencial",
				"Antiguedad",
				"Cotizable",
				"Diferencia",
				"Factor de Complemento",
				"Reintegro",
				"Total",
				"Tipo de recepcion",
				"Observaciones "
			));

			foreach ($complementos as $complemento) {
          # code...
				$observaciones = AffiliateObservation::where('affiliate_id', $complemento->affiliate_id)->whereIn('observation_type_id', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15])->get();
				$obs = "";
				foreach ($observaciones as $observacion) {
            # code...
            //Log::info($observacion->observationType->name);
					$obs = $obs . " | " . $observacion->observationType->name;
				}

				$base_wage = DB::table("base_wages")->where("id", $complemento->base_wage_id)->first();

				$sueldo_base = "";
				if ($base_wage) {
					$sueldo_base = $base_wage->amount;
				}

				$aplicant = EconomicComplementApplicant::where('economic_complement_id', $complemento->id)->first();



				array_push($rows, array(
					$complemento->id,
					$complemento->code,
					$complemento->reception_date,
					$aplicant->identity_card,
					$aplicant->city_identity_card->second_shortened,
					$aplicant->first_name,
					$aplicant->second_name,
					$aplicant->last_name,
					$aplicant->mothers_last_name,
					$aplicant->surname_husband,
					$complemento->city->name,
					$complemento->economic_complement_modality->shortened,
					$complemento->category->name,
					$sueldo_base,
					$complemento->affiliate->degree->shortened,
					$complemento->affiliate->pension_entity->name,
					$complemento->affiliate->gender,

					$complemento->sub_total_rent,
					$complemento->dignity_pension,
					$complemento->total_rent,
					$complemento->total_rent_calc,
					$complemento->salary_reference,
					$complemento->seniority,
					$complemento->salary_quotable,
					$complemento->diference,
					$complemento->complementary_factor,
					$complemento->reimbursement,
					$complemento->total,
					$complemento->reception_type,
					$obs
				));

			}

			Excel::create('Reporte Observados ' . date("Y-m-d H:i:s"), function ($excel) {
				global $rows;
				$excel->sheet('No revisados', function ($sheet) {
					global $rows;

					$sheet->fromArray($rows, null, 'A1', false, false);
					$sheet->cells('A1:AE1', function ($cells) {

                    // manipulate the range of cells
						$cells->setBackground('#058A37');
						$cells->setFontColor('#ffffff');

					});

				});
			})->download('xls');

		}
	}


	public function payrollLegalGuardianBank()

	{
		global $rows, $i;
		$eco = EconomicComplement::where('eco_com_procedure_id', '=', 6)
            //->whereNotNull('review_date')
			->where('wf_current_state_id', '=', '3')
			->where('state', 'like', 'Edited')
			->where('has_legal_guardian', '=', true)
			->where('has_legal_guardian_s', '=', false)
			->where('economic_complements.total', '>', 0)
			->whereRaw('economic_complements.total_rent::numeric < economic_complements.salary_quotable::numeric')
			->get();
		$rows[] = array('Nro', 'C.I.', 'Nombre Completo Poderdante', 'C.I.', 'Nombre Completo Apoderado', 'Regional', 'Grado', 'Tipo Renta', 'Complemento Economico');
		$i = 1;
		foreach ($eco as $e) {
			if (!$e->affiliate->observations()->whereNotIn('observation_type_id', [1, 2, 3, 12, 13])->where('is_enabled', '=', false)->whereNull('deleted_at')->get()->count()) {
				$app = $e->economic_complement_applicant;
				$apo = $e->economic_complement_legal_guardian;
				$data = new stdClass;
				$data->index = $i++;
				$data->ci_app = $app->identity_card . ' ' . $app->city_identity_card->first_shortened;
				$data->name_app = $app->getFullName();
				$data->ci_apo = $apo->identity_card . ' ' . $apo->city_identity_card->first_shortened;
				$data->name = $apo->getFullName();
				$data->city = $e->city->name;
				$data->degree = $e->degree->shortened;
				$data->eco_com_type = strtoupper($e->economic_complement_modality->economic_complement_type->name);
				$data->total = $e->total;
            // $rows[] = get_object_vars($data);
				$rows[] = (array)($data);

			}
		}
		Excel::create('Planilla de casos de Apoderados y poderdantes Banco', function ($excel) {
			global $rows;
			$excel->sheet('Apoderados Banco', function ($sheet) {
				global $rows;
				$sheet->fromArray($rows, null, 'A1', false, false);
				$sheet->cells('A1:I1', function ($cells) {
					$cells->setBackground('#058A37');
					$cells->setFontColor('#ffffff');
					$cells->setFontWeight('bold');
				});
				$sheet->setAllBorders('thin');
			});
		})->download('xls');
	}



	public function export_observation_bank(Request $request)
	{
		global $year, $semester;

		$afi = DB::table('eco_com_applicants')
			->select(DB::raw("economic_complements.id,economic_complements.affiliate_id,economic_complements.semester,cities0.second_shortened as regional,eco_com_applicants.identity_card,cities1.first_shortened as ext,concat_ws(' ', NULLIF(eco_com_applicants.first_name,null), NULLIF(eco_com_applicants.second_name, null), NULLIF(eco_com_applicants.last_name, null), NULLIF(eco_com_applicants.mothers_last_name, null), NULLIF(eco_com_applicants.surname_husband, null)) as full_name,economic_complements.total as importe,eco_com_modalities.shortened as modality,degrees.shortened as degree, "))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('cities as cities0', 'economic_complements.city_id', '=', 'cities0.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'eco_com_applicants.city_identity_card_id', '=', 'cities1.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->whereYear('economic_complements.year', '=', $year)
			->where('economic_complements.semester', '=', $semester)
			->where('economic_complements.workflow_id', '=', 1)
			->where('economic_complements.wf_current_state_id', 2)
			->where('economic_complements.state', 'Edited')
			->where('economic_complements.total', '>', 0)
			->whereRaw('economic_complements.total_rent::numeric < economic_complements.salary_quotable::numeric')
			->whereRaw("not exists(select affiliates.id from affiliate_observations where affiliates.id = affiliate_observations.affiliate_id and affiliate_observations.observation_type_id IN(1,2,3,12,13,14,15) and is_enabled = false ) ")
			->whereNotNull('economic_complements.review_date')->get();
		return $request->year;

	}

	public function export_aps_availability()
	{
		global $i, $afi;

		Excel::create('Muserpol_aps_disponibilidad', function ($excel) {
			global $j;
			$j = 2;
			$excel->sheet("APS_DISPONIBILIDAD", function ($sheet) {
				global $j, $i, $afi;
				$i = 1;
				$sheet->row(1, array('NRO', 'TIPO_ID', 'NUM_ID', 'EXTENSION', 'CUA', 'PRIMER_APELLIDO_T', 'SEGUNDO_APELLIDO_T', 'PRIMER_NOMBRE_T', 'SEGUNDO_NOMBRE_T', 'APELLIDO_CASADA_T', 'FECHA_NACIMIENTO_T'));
				$afi = DB::table('affiliates')
					->select(DB::raw("distinct on (affiliates.identity_card) affiliates.identity_card, concat(repeat('0',13-length(RTRIM(affiliates.identity_card))), RTRIM(affiliates.identity_card)) as identity_card, CITIES.third_shortened, cast(concat(repeat('0',9-length(RTRIM(cast(affiliates.nua as text)))), RTRIM(cast(affiliates.nua as text))) as text) as nua, affiliates.last_name,affiliates.mothers_last_name,affiliates.first_name,affiliates.second_name,affiliates.surname_husband,replace(cast(affiliates.birth_date as text), '-', '') as birth_date"))
					->leftJoin('units', 'affiliates.unit_id', '=', 'units.id')
					->leftJoin('breakdowns', 'units.breakdown_id', '=', 'breakdowns.id')
					->leftJoin('cities', 'affiliates.city_identity_card_id', '=', 'cities.id')
					->leftJoin('affiliate_observations', 'affiliates.id', '=', 'affiliate_observations.affiliate_id')
					->leftJoin('observation_types', 'affiliate_observations.observation_type_id', '=', 'observation_types.id')
                    // ->where('breakdowns.id','=', 1)
                    // ->whereNull('affiliates.pension_entity_id')
					->where('affiliates.nua', '>', 0)
					->whereRaw("(observation_types.id = 16 or breakdowns.id = 1) and not exists(SELECT 1 FROM economic_complements where economic_complements.affiliate_id = affiliates.id)")
					->get();
				foreach ($afi as $datos) {
					$sheet->row($j, array($i, "I", $datos->identity_card, $datos->third_shortened, $datos->nua, $datos->last_name, $datos->mothers_last_name, $datos->first_name, $datos->second_name, $datos->surname_husband, $datos->birth_date));
					$j++;
					$i++;
				}
			});
		})->export('xlsx');
		Session::flash('message', "Exportación Exitosa");
		return redirect('economic_complement');
	}

//EXPORT BY WORKFLOWS ID
	public function export_payment_bank(Request $request)
	{
		global $j, $ecom;
		$j = 2;
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit,  economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_credit,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
              //->where('economic_complements.eco_com_procedure_id','=', 2)
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
			->where('economic_complements.workflow_id', '=', 1)
			->where('economic_complements.eco_com_state_id', '=', 1)
			->get();
  //dd($ecom);
		if (sizeof($ecom) > 0) {
			Excel::create('Pagados_por_Banco', function ($excel) {
				global $ecom;
				$excel->sheet("Pagado_banco", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros pagados por banco");
			return redirect('economic_complement');
		}

	}


	public function export_rezagados(Request $request)
	{
		global $j, $ecom;
		$j = 2;
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit,  economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_credit,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
              //->where('economic_complements.eco_com_procedure_id','=', 2)
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
			->where('economic_complements.workflow_id', '=', 2)
			->get();
  //dd($ecom);
		if (sizeof($ecom) > 0) {
			Excel::create('Rezagados', function ($excel) {
				global $ecom;
				$excel->sheet("Rezagados", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros para exportar rezagados");
			return redirect('economic_complement');
		}

	}

	public function export_payment_home(Request $request)
	{
		global $j, $ecom;
		$j = 2;
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
              //->where('economic_complements.eco_com_procedure_id','=', 2)
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
			->where('economic_complements.eco_com_state_id', '=', 17)
			->get();
  //dd($ecom);
		if (sizeof($ecom) > 0) {
			Excel::create('Pagados_Domicilio', function ($excel) {
				global $ecom;
				$excel->sheet("Pagados_Domicilio", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros para exportar rezagados");
			return redirect('economic_complement');
		}

	}

	public function export_wf_gral_banco(Request $request) // EXPORTAR PAGADOS POR BANCO Y REZAGADOS DEL BANCO PARA GENERAR LA PLANILLA GRAL. BANCO
	{
		global $j, $ecom;
		$j = 2;
		$ecom0 = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
			->where('economic_complements.workflow_id', '=', 1)
			->where('economic_complements.eco_com_state_id', '=', 1)
			->get();

		$ecom1 = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
              //->where('economic_complements.eco_com_procedure_id','=', 2)
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
			->where('economic_complements.workflow_id', '=', 2)
			->get();
		$ecom = array_merge($ecom0, $ecom1);

		if (sizeof($ecom) > 0) {
			Excel::create('Planilla_Gral_Banco', function ($excel) {
				global $ecom;
				$excel->sheet("Planilla_Gral_Banco", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}

	/**
	 * @param  Request
	 * @return [type]
	 */
	public function export_wf_adicionales(Request $request) // EXPORTAR ADICIONALES POR WF
	{
		global $j, $ecom;
		$j = 2;
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
			->where('economic_complements.workflow_id', '=', 3)
			->get();


		if (sizeof($ecom) > 0) {
			Excel::create('Planilla_Adicionales', function ($excel) {
				global $ecom;
				$excel->sheet("Tramite_Adicionales", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}

	public function export_wfpoder(Request $request) // EXPORTAR PAGADOS CON PODER
	{
		global $j, $ecom;
		$j = 2;
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
			->where('economic_complements.workflow_id', '=', 1)
			->where('economic_complements.eco_com_state_id', '=', 1)
			->where('economic_complements.has_legal_guardian', '=', true)
			->get();


		if (sizeof($ecom) > 0) {
			Excel::create('Pagados_con_poder', function ($excel) {
				global $ecom;
				$excel->sheet("Pagados_poder_banco", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}

	public function export_wfmora_prestamos(Request $request) // EXPORTAR PAGADOS CON PODER
	{
		global $j, $ecom;
		$j = 2;
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
			->where('economic_complements.workflow_id', '=', 1)
			->where('economic_complements.eco_com_state_id', '=', 1)
			->where('economic_complements.amount_loan', '>', 0)
			->get();


		if (sizeof($ecom) > 0) {
			Excel::create('Pagados_amort_prestamos', function ($excel) {
				global $ecom;
				$excel->sheet("Pagados_amort_prestamos", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}


	public function export_wfrep_fondos(Request $request) // EXPORTAR PAGADOS CON AMORIZACION REP. FONDOS
	{
		global $j, $ecom;
		$j = 2;
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
			->where('economic_complements.workflow_id', '=', 1)
			->where('economic_complements.eco_com_state_id', '=', 1)
			->where('economic_complements.amount_replacement', '>', 0)
			->get();


		if (sizeof($ecom) > 0) {
			Excel::create('Pagados_amort_fondos', function ($excel) {
				global $ecom;
				$excel->sheet("Pagados_amort_fondos", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}

	public function export_wfcontabilidad(Request $request) // EXPORTAR PAGADOS CON AMORIZACION REP. FONDOS
	{
		global $j, $ecom;
		$j = 2;
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
			->where('economic_complements.workflow_id', '=', 1)
			->where('economic_complements.eco_com_state_id', '=', 1)
			->where('economic_complements.amount_accounting', '>', 0)
			->get();


		if (sizeof($ecom) > 0) {
			Excel::create('Pagados_amort_contabi', function ($excel) {
				global $ecom;
				$excel->sheet("Pagados_amort_contabi", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}

	public function export_wfnormal(Request $request) // EXPORTAR PAGADOS CON AMORIZACION REP. FONDOS
	{
		global $j, $ecom;
		$j = 2;
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
			->where('economic_complements.workflow_id', '=', 1)
			->where('economic_complements.eco_com_state_id', '=', 1)
			->whereNull('economic_complements.amount_accounting')
			->whereNull('economic_complements.amount_loan')
			->whereNull('economic_complements.amount_replacement')
              //->where('economic_complements.has_legal_guardian', '=',false)
			->get();


		if (sizeof($ecom) > 0) {
			Excel::create('Pagados_Normal', function ($excel) {
				global $ecom;
				$excel->sheet("Pagados_Normal", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}

	public function export_wf_sup_fondo(Request $request)
	{

		global $j, $ecom;
		$j = 2;

		$aff = DB::table('affiliates')
			->leftJoin('affiliate_observations', 'affiliates.id', '=', 'affiliate_observations.affiliate_id')
			->leftJoin('observation_types', 'affiliate_observations.observation_type_id', '=', 'observation_types.id')
			->where('affiliate_observations.is_enabled', '=', false)
			->where('observation_types.id', '=', 13)
			->select('affiliates.id')
			->get();
		$afff = [];
		foreach ($aff as $val) {
			array_push($afff, $val->id);
		}
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
              // ->where('economic_complements.workflow_id','=',3)
			->where('economic_complements.state', '=', 'Edited')
			->whereNotNull('economic_complements.review_date')
			->where('economic_complements.review_date', '<=', "2017-08-25 23:58:00")
			->whereIn('economic_complements.affiliate_id', $afff)
			->whereRaw("not exists(select affiliates.id from affiliate_observations where affiliates.id = affiliate_observations.affiliate_id and affiliate_observations.observation_type_id IN(14,15) and is_enabled = false ) ")
			->where('economic_complements.total', '>', 0)
			->get(); 
  // dd($ecom);
		if (sizeof($ecom) > 0) {
			Excel::create('Suspendidos_por_reposicion_de_fondos', function ($excel) {
				global $ecom;
				$excel->sheet("susp_repo_fondos", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}
	public function export_wf_sup_prestamos(Request $request)
	{

		global $j, $ecom;
		$j = 2;

		$aff = DB::table('affiliates')
			->leftJoin('affiliate_observations', 'affiliates.id', '=', 'affiliate_observations.affiliate_id')
			->leftJoin('observation_types', 'affiliate_observations.observation_type_id', '=', 'observation_types.id')
			->where('affiliate_observations.is_enabled', '=', false)
			->where('observation_types.id', '=', 2)
			->select('affiliates.id')
			->get();
		$afff = [];
		foreach ($aff as $val) {
			array_push($afff, $val->id);
		}
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
              // ->where('economic_complements.workflow_id','=',3)
			->where('economic_complements.state', '=', 'Edited')
			->whereNotNull('economic_complements.review_date')
			->where('economic_complements.review_date', '<=', "2017-08-25 23:58:00")
			->whereIn('economic_complements.affiliate_id', $afff)
			->whereRaw("not exists(select affiliates.id from affiliate_observations where affiliates.id = affiliate_observations.affiliate_id and affiliate_observations.observation_type_id IN(14,15) and is_enabled = false ) ")
			->where('economic_complements.total', '>', 0)
			->get(); 
  // dd($ecom);
		if (sizeof($ecom) > 0) {
			Excel::create('Suspendidos_por_prestamos', function ($excel) {
				global $ecom;
				$excel->sheet("susp_prestamos", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}
	public function export_wf_sup(Request $request)
	{

		global $j, $ecom;
		$j = 2;

		$aff = DB::table('affiliates')
			->leftJoin('affiliate_observations', 'affiliates.id', '=', 'affiliate_observations.affiliate_id')
			->leftJoin('observation_types', 'affiliate_observations.observation_type_id', '=', 'observation_types.id')
			->where('affiliate_observations.is_enabled', '=', false)
			->whereIn('observation_types.id', [1, 2, 13])
			->select('affiliates.id')
			->get();
		$afff = [];
		foreach ($aff as $val) {
			array_push($afff, $val->id);
		}
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
              // ->where('economic_complements.workflow_id','=',3)
			->where('economic_complements.state', '=', 'Edited')
			->whereNotNull('economic_complements.review_date')
			->where('economic_complements.review_date', '<=', "2017-08-25 23:58:00")
			->whereIn('economic_complements.affiliate_id', $afff)
			->whereRaw("not exists(select affiliates.id from affiliate_observations where affiliates.id = affiliate_observations.affiliate_id and affiliate_observations.observation_type_id IN(14,15) and is_enabled = false ) ")
			->where('economic_complements.total', '>', 0)
			->get(); 
  // dd($ecom);
		if (sizeof($ecom) > 0) {
			Excel::create('Suspendidos', function ($excel) {
				global $ecom;
				$excel->sheet("suspendidos", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}
	public function export_wf_sup_contabilidad(Request $request)
	{

		global $j, $ecom;
		$j = 2;

		$aff = DB::table('affiliates')
			->leftJoin('affiliate_observations', 'affiliates.id', '=', 'affiliate_observations.affiliate_id')
			->leftJoin('observation_types', 'affiliate_observations.observation_type_id', '=', 'observation_types.id')
			->where('affiliate_observations.is_enabled', '=', false)
			->where('observation_types.id', '=', 1)
			->select('affiliates.id')
			->get();
		$afff = [];
		foreach ($aff as $val) {
			array_push($afff, $val->id);
		}
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
              // ->where('economic_complements.workflow_id','=',3)
			->where('economic_complements.state', '=', 'Edited')
			->whereNotNull('economic_complements.review_date')
			->where('economic_complements.review_date', '<=', "2017-08-25 23:58:00")
			->whereIn('economic_complements.affiliate_id', $afff)
			->whereRaw("not exists(select affiliates.id from affiliate_observations where affiliates.id = affiliate_observations.affiliate_id and affiliate_observations.observation_type_id IN(14,15) and is_enabled = false ) ")
			->where('economic_complements.total', '>', 0)
			->get(); 
  // dd($ecom);
		if (sizeof($ecom) > 0) {
			Excel::create('Suspendidos_por_contabilidad', function ($excel) {
				global $ecom;
				$excel->sheet("susp_cont", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}

	public function export_wf_rez_contabilidad(Request $request)
	{

		global $j, $ecom;
		$j = 2;

		$aff = DB::table('affiliates')
			->leftJoin('affiliate_observations', 'affiliates.id', '=', 'affiliate_observations.affiliate_id')
			->leftJoin('observation_types', 'affiliate_observations.observation_type_id', '=', 'observation_types.id')
			->where('affiliate_observations.is_enabled', '=', true)
			->where('observation_types.id', '=', 1)
			->select('affiliates.id')
			->get();
		$afff = [];
		foreach ($aff as $val) {
			array_push($afff, $val->id);
		}
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,eco_com_applicants.phone_number as phone,eco_com_applicants.cell_phone_number as cell_phone,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
              // ->where('economic_complements.workflow_id','=',3)
			->where('economic_complements.state', '=', 'Edited')
			->whereNotNull('economic_complements.review_date')
			->where('economic_complements.review_date', '<=', "2017-08-25 23:58:00")
			->whereIn('economic_complements.affiliate_id', $afff)
			->where('economic_complements.eco_com_state_id', '=', 15)
			->get(); 
  // dd($ecom);
		if (sizeof($ecom) > 0) {
			Excel::create('Rezagados_contabilidad', function ($excel) {
				global $ecom;
				$excel->sheet("rez_cont", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'TELEFONO', 'CELULAR', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->phone, $datos->cell_phone, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}
	public function export_wf_rez_prestamos(Request $request)
	{

		global $j, $ecom;
		$j = 2;

		$aff = DB::table('affiliates')
			->leftJoin('affiliate_observations', 'affiliates.id', '=', 'affiliate_observations.affiliate_id')
			->leftJoin('observation_types', 'affiliate_observations.observation_type_id', '=', 'observation_types.id')
			->where('affiliate_observations.is_enabled', '=', true)
			->where('observation_types.id', '=', 2)
			->select('affiliates.id')
			->get();
		$afff = [];
		foreach ($aff as $val) {
			array_push($afff, $val->id);
		}
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,eco_com_applicants.phone_number as phone,eco_com_applicants.cell_phone_number as cell_phone,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
              // ->where('economic_complements.workflow_id','=',3)
			->where('economic_complements.state', '=', 'Edited')
			->whereNotNull('economic_complements.review_date')
			->where('economic_complements.eco_com_state_id', '=', 15)
			->where('economic_complements.review_date', '<=', "2017-08-25 23:58:00")
			->whereIn('economic_complements.affiliate_id', $afff)

			->get(); 
  // dd($ecom);
		if (sizeof($ecom) > 0) {
			Excel::create('Rezagados_prestamos', function ($excel) {
				global $ecom;
				$excel->sheet("rez_prestamos", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'TELEFONO', 'CELULAR', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->phone, $datos->cell_phone, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}
	public function export_wf_rez_fondos(Request $request)
	{

		global $j, $ecom;
		$j = 2;

		$aff = DB::table('affiliates')
			->leftJoin('affiliate_observations', 'affiliates.id', '=', 'affiliate_observations.affiliate_id')
			->leftJoin('observation_types', 'affiliate_observations.observation_type_id', '=', 'observation_types.id')
			->where('affiliate_observations.is_enabled', '=', true)
			->where('observation_types.id', '=', 13)
			->select('affiliates.id')
			->get();
		$afff = [];
		foreach ($aff as $val) {
			array_push($afff, $val->id);
		}
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,eco_com_applicants.phone_number as phone,eco_com_applicants.cell_phone_number as cell_phone,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
              // ->where('economic_complements.workflow_id','=',3)
			->where('economic_complements.state', '=', 'Edited')
			->whereNotNull('economic_complements.review_date')
			->where('economic_complements.review_date', '<=', "2017-08-25 23:58:00")
			->whereIn('economic_complements.affiliate_id', $afff)
			->where('economic_complements.eco_com_state_id', '=', 15)
			->whereRaw("not exists(select affiliates.id from affiliate_observations where affiliates.id = affiliate_observations.affiliate_id and affiliate_observations.observation_type_id IN(14,15) and is_enabled = false ) ")
			->get(); 
  // dd($ecom);
		if (sizeof($ecom) > 0) {
			Excel::create('Rezagados_rep_fondos', function ($excel) {
				global $ecom;
				$excel->sheet("rez_rep_fondos", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'TELEFONO', 'CELULAR', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->phone, $datos->cell_phone, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}
	public function export_wf_rez_normal(Request $request)
	{

		global $j, $ecom;
		$j = 2;

		$aff = DB::table('affiliates')
			->leftJoin('affiliate_observations', 'affiliates.id', '=', 'affiliate_observations.affiliate_id')
			->leftJoin('observation_types', 'affiliate_observations.observation_type_id', '=', 'observation_types.id')
                // ->where('affiliate_observations.is_enabled','=',true)
			->whereNotIn('observation_types.id', [1, 2, 13])
			->select('affiliates.id')
			->get();
		$afff = [];
		foreach ($aff as $val) {
			array_push($afff, $val->id);
		}
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,eco_com_applicants.phone_number as phone,eco_com_applicants.cell_phone_number as cell_phone,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
              // ->where('economic_complements.workflow_id','=',3)
			->where('economic_complements.state', '=', 'Edited')
			->whereNotNull('economic_complements.review_date')
			->where('economic_complements.review_date', '<=', "2017-08-25 23:58:00")
              // ->whereIn('economic_complements.affiliate_id', $afff)
			->where('economic_complements.eco_com_state_id', '=', 15)
			->whereRaw("not exists(select affiliates.id from affiliate_observations where affiliates.id = affiliate_observations.affiliate_id and affiliate_observations.observation_type_id IN(1,2,13) and is_enabled = true ) ")
			->get(); 
  // dd($ecom);
		if (sizeof($ecom) > 0) {
			Excel::create('Rezagados_normal', function ($excel) {
				global $ecom;
				$excel->sheet("rez_normal", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'TELEFONO', 'CELULAR', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->phone, $datos->cell_phone, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}
	public function export_wf_rez(Request $request)
	{

		global $j, $ecom;
		$j = 2;

		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,eco_com_applicants.phone_number as phone,eco_com_applicants.cell_phone_number as cell_phone,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
			->where('economic_complements.workflow_id', '=', 2)
              // ->where('economic_complements.state','=','Edited')
              // ->whereNotNull('economic_complements.review_date')
              // ->where('economic_complements.review_date','<=', "2017-08-25 23:58:00")
              // ->where('economic_complements.eco_com_state_id','=',15)       
			->get(); 
  // dd($ecom);
		if (sizeof($ecom) > 0) {
			Excel::create('Rezagados', function ($excel) {
				global $ecom;
				$excel->sheet("rezagados", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'TELEFONO', 'CELULAR', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->phone, $datos->cell_phone, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}

	public function export_wfamort_total(Request $request) // EXPORTAR PAGADOS CON AMORIZACION REP. FONDOS
	{
		global $j, $ecom;
		$j = 2;
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
			->where('economic_complements.total', '=', 0)
			->get();


		if (sizeof($ecom) > 0) {
			Excel::create('Amortizados_100_porciento', function ($excel) {
				global $ecom;
				$excel->sheet("Amortizados_100_porciento", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}

	public function export_wfamort_total_prestamos(Request $request) // EXPORTAR PAGADOS CON AMORIZACION REP. FONDOS
	{
		global $j, $ecom;
		$j = 2;
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
			->where('economic_complements.total', '=', 0)
			->where('economic_complements.amount_loan', '>', 0)
			->get();


		if (sizeof($ecom) > 0) {
			Excel::create('Amortizado_total_prestamos', function ($excel) {
				global $ecom;
				$excel->sheet("Amortizado_total_prestamos", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}

	public function export_wfamort_total_fondos(Request $request) // EXPORTAR PAGADOS CON AMORIZACION REP. FONDOS
	{
		global $j, $ecom;
		$j = 2;
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
			->where('economic_complements.total', '=', 0)
			->where('economic_complements.amount_replacement', '>', 0)
			->get();


		if (sizeof($ecom) > 0) {
			Excel::create('Amortizado_total_reposicion', function ($excel) {
				global $ecom;
				$excel->sheet("Amortizado_total_reposicion", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}

	public function export_wfamort_total_contabilidad(Request $request) // EXPORTAR PAGADOS CON AMORIZACION REP. FONDOS
	{
		global $j, $ecom;
		$j = 2;
		$ecom = DB::table('eco_com_applicants')
			->Select(DB::raw('economic_complements.code,eco_com_applicants.identity_card,cities2.first_shortened as ext,eco_com_applicants.first_name,eco_com_applicants.second_name,eco_com_applicants.last_name,eco_com_applicants.mothers_last_name,eco_com_applicants.surname_husband,cities1.name as regional,degrees.shortened as degree,categories.name as category,eco_com_modalities.shortened as modality,pension_entities.name as pension_entity,economic_complements.total,economic_complements.amount_loan,economic_complements.amount_accounting, economic_complements.amount_credit, economic_complements.amount_replacement, (coalesce(economic_complements.total,0) + coalesce(economic_complements.amount_loan,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_accounting,0) + coalesce(economic_complements.amount_replacement,0)) as subtotal'))
			->leftJoin('economic_complements', 'eco_com_applicants.economic_complement_id', '=', 'economic_complements.id')
			->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
			->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
			->leftJoin('cities as cities1', 'economic_complements.city_id', '=', 'cities1.id')
			->leftJoin('cities as cities2', 'eco_com_applicants.city_identity_card_id', '=', 'cities2.id')
			->leftJoin('degrees', 'economic_complements.degree_id', '=', 'degrees.id')
			->leftJoin('categories', 'economic_complements.category_id', '=', 'categories.id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->whereYear('economic_complements.year', '=', $request->year)
			->where('economic_complements.semester', '=', $request->semester)
			->where('economic_complements.total', '=', 0)
			->where('economic_complements.amount_accounting', '>', 0)
			->get();


		if (sizeof($ecom) > 0) {
			Excel::create('Amortizado_total_contabilidad', function ($excel) {
				global $ecom;
				$excel->sheet("Amortizado_total_contabilidad", function ($sheet) {
					global $i, $j, $ecom;
					$i = 1;
					$sheet->row(1, array('NRO', 'CODIGO_TRAMITE', 'CI', 'EXT', 'PRIMER_NOMBRE', 'SEGUNDO_NOMBRE', 'PATERNO', 'MATERNO', 'APELLIDO_DE_CASADO', 'REGIONAL', 'GRADO', 'CATEGORIA', 'TIPO_RENTA', 'ENTE_GESTOR', 'SUBTOTAL', 'AMORTIZACION_PRESTAMOS', 'AMORTIZACION_CONTABILIDAD', 'AMORTIZACION_PAGO_A_FUTURO', 'REPOSICION_FONDO', 'TOTAL'));

					foreach ($ecom as $datos) {
						$sheet->row($j, array($i, $datos->code, $datos->identity_card, $datos->ext, $datos->first_name, $datos->second_name, $datos->last_name, $datos->mothers_last_name, $datos->surname_husband, $datos->regional, $datos->degree, $datos->category, $datos->modality, $datos->pension_entity, $datos->subtotal, $datos->amount_loan, $datos->amount_accounting, $datos->amount_credit, $datos->amount_replacement, $datos->total));
						$j++;
						$i++;
					}


				});
			})->export('xlsx');
			Session::flash('message', "Exportación Exitosa");
			return redirect('economic_complement');
		} else {
			Session::flash('message', "No existen registros");
			return redirect('economic_complement');
		}
	}




	public function export_senasir(Request $request)
	{
		global $year, $semester, $applicants;
		$year = $request->year;
		$semester = $request->semester;
		Excel::create('Senasir_' . date("Y-m-d H:i:s"), function ($excel) {
			global $year, $semester, $applicants;
			$applicants = EconomicComplement::leftJoin('eco_com_procedures', 'economic_complements.eco_com_procedure_id', '=', 'eco_com_procedures.id')
				->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
				->leftJoin('eco_com_types', 'eco_com_modalities.eco_com_type_id', '=', 'eco_com_types.id')
				->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
				->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
				->leftJoin('eco_com_applicants', 'economic_complements.id', '=', 'eco_com_applicants.economic_complement_id')
				->leftJoin('cities', 'economic_complements.city_id', '=', 'cities.id')
				->where('pension_entities.id', '=', 5)
				->where('eco_com_types.id', '=', 2)
				->whereyear('eco_com_procedures.year', '=', $year)
				->where('eco_com_procedures.semester', '=', $semester)
				->select(DB::raw("trim(regexp_replace(CONCAT(eco_com_applicants.first_name,' ',eco_com_applicants.second_name,' ',eco_com_applicants.last_name,' ',eco_com_applicants.mothers_last_name,' ',eco_com_applicants.surname_husband),'( )+' , ' ', 'g')) as nombre_derechohabiente,
            eco_com_applicants.identity_card as ci_derechohabiente,
            trim(regexp_replace(CONCAT(affiliates.first_name,' ',affiliates.second_name,' ',affiliates.last_name,' ',affiliates.mothers_last_name,' ',affiliates.surname_husband),'( )+' , ' ', 'g')) as nombre_causahabiente,
            affiliates.identity_card as ci_causahabiente,
            affiliates.birth_date as fecha_nac_causahabiente, 
            cities.name as regional,
            economic_complements.code as nro_tramite
            "))
				->get();
			$excel->sheet('Derechohabientes', function ($sheet) {
				global $applicants;
				$sheet->fromArray($applicants);
				$sheet->cells('A1:G1', function ($cells) {
					$cells->setBackground('#058A37');
					$cells->setFontColor('#ffffff');
					$cells->setFontWeight('bold');
				});
			});
		})->export('xlsx');

		Session::flash('message', "Importación Exitosa");
		return redirect('economic_complement');
	}
	public function export_amortizados_reposicion()
	{
		global $afiliados;
		$afiliados = Devolution::leftJoin('affiliates', 'affiliates.id', '=', 'devolutions.affiliate_id')
			->leftJoin('pension_entities', 'affiliates.pension_entity_id', '=', 'pension_entities.id')
			->leftJoin('cities', 'affiliates.city_identity_card_id', '=', 'cities.id')
			->leftJoin('cities as cities2', 'affiliates.city_birth_id', '=', 'cities2.id')
			->leftJoin('degrees', 'degrees.id', '=', 'affiliates.degree_id')
			->leftJoin('categories', 'categories.id', '=', 'affiliates.category_id')

			->whereRaw('devolutions.total > devolutions.balance')
			->select('affiliates.identity_card as ci', 'cities.first_shortened as extension', 'affiliates.first_name as primer_nombre', 'affiliates.second_name as segundo_nombre', 'affiliates.last_name as paterno', 'affiliates.mothers_last_name as materno', 'cities2.name as ciudad', 'degrees.name as grado', 'categories.name as categoria', 'devolutions.total', 'devolutions.balance as deuda')
			->get()->toArray();
		Util::excelDownload('Amortizados', 'Amortizados', $afiliados, ['J', 'k']);

		Excel::create('Amortizados_' . date("Y-m-d H:i:s"), function ($excel) {

			$excel->sheet('Amortizados', function ($sheet) {
				global $afiliados;
				$sheet->setColumnFormat(array(
					'J' => '#,##0.00', //1.000,10 (depende de windows)
                  // 'J' => \PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1  //1.000,10
				));
				$sheet->prependRow(1, array_keys($afiliados[0]));
				$sheet->rows($afiliados);
				$sheet->cells('A1:K1', function ($cells) {
					$cells->setBackground('#058A37');
					$cells->setFontColor('#ffffff');
					$cells->setFontWeight('bold');
				});
			});

		})->download('xlsx');

	}











	//VERIFICANDO COMPONENTES REGISTRADOS MANUALMENTE EN BD CON EL EXCEL DE APS

	public static function check_aps_excel(Request $request)
	{

		if ($request->hasFile('archive')) 
		{
			global $year, $semester, $results, $i, $afi, $list;
			$reader = $request->file('archive');
			$filename = $reader->getRealPath();
			$year = $request->year;
			$semester = $request->semester;
			Log::info("Reading excel ...");
			Excel::load($filename, function ($reader) 
			{
				global $results, $i, $afi, $list;
				ini_set('memory_limit', '-1');
				ini_set('max_execution_time', '-1');
				ini_set('max_input_time', '-1');
				set_time_limit('-1');
				$results = collect($reader->get());
			});
			Log::info("done read excel");

			$afi;
			$found = 0;
			$nofound = 0;
			$procedure = EconomicComplementProcedure::whereYear('year', '=', $year)->where('semester', '=', $semester)->first();
			foreach ($results as $datos) 
			{
				$nua = ltrim((string)$datos->nrosip_titular, "0");
				$ci = explode("-", ltrim($datos->nro_identificacion, "0"));
				$ci1 = $ci[0];
				$afi = DB::table('economic_complements')
					->select(DB::raw('economic_complements.id,economic_complements.code,economic_complements.reception_date,economic_complements.total_rent,economic_complements.aps_disability as invalidez,economic_complements.aps_total_cc,economic_complements.aps_total_fsa,economic_complements.aps_total_fs,economic_complements.total, eco_com_types.id as type,affiliates.identity_card as ci_afi,affiliates.first_name as afi_nombres,affiliates.last_name as afi_paterno,affiliates.mothers_last_name as afi_materno'))
					->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
					->leftJoin('eco_com_modalities', 'economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
					->leftJoin('eco_com_types', 'eco_com_modalities.eco_com_type_id', '=', 'eco_com_types.id')
					->whereRaw("split_part(LTRIM(affiliates.identity_card,'0'), '-',1) = '" . $ci1 . "'")
					->whereRaw("LTRIM(affiliates.nua::text,'0') ='" . $nua . "'")
					->where('affiliates.pension_entity_id', '!=', 5)
					->where('economic_complements.eco_com_procedure_id','=',$procedure->id)
					->where('economic_complements.total_rent','>',0)
					->first();
				if ($afi) 
				{
					//$ecomplement = EconomicComplement::where('id', '=', $afi->id)->first();
				/*	if((float)$afi->total_rent == (float)round($datos->total_pension,2) && (float)$afi->aps_total_cc == (float)round($datos->total_cc,2) && (float)$afi->aps_total_fsa == (float)round($datos->total_fsa,2) && (float)$afi->aps_total_fs == (float)round($datos->total_fs,2))
					{	$found++;
						if($afi->aps_disability >0 ){
							Log::info($afi->ci_afi." tiene disability TRUE");
						}
					}
					else
					{  // dd($afi->total_rent."=".$datos->total_pension);
						if($afi->aps_disability >0 ){
							Log::info($afi->ci_afi." tiene disability ");
						}else{
							Log::info($afi->ci_afi . ' '.(float)$afi->total_rent. ' == '. (float)round($datos->total_pension,2) . ' ---- '. (float)$afi->aps_total_cc . ' == '. (float)round($datos->total_cc,2). ' ----- '. (float)$afi->aps_total_fsa. ' == '.  (float)round($datos->total_fsa,2). ' ----- '. (float)$afi->aps_total_fs . ' == '. (float)round($datos->total_fs));
						$nofound++;
						//dd($afi);
						$list[] = (array)$afi;
						}

						
						
					}*/

					if((float)$afi->total_rent != (float)round($datos->total_pension,2) || (float)$afi->aps_total_cc != (float)round($datos->total_cc,2) || (float)$afi->aps_total_fsa != (float)round($datos->total_fsa,2) || (float)$afi->aps_total_fs != (float)round($datos->total_fs,2))
					{	$found++;
						Log::info($afi->ci_afi . ' '.(float)$afi->total_rent. ' == '. (float)round($datos->total_pension,2) . ' ---- '. (float)$afi->aps_total_cc . ' == '. (float)round($datos->total_cc,2). ' ----- '. (float)$afi->aps_total_fsa. ' == '.  (float)round($datos->total_fsa,2). ' ----- '. (float)$afi->aps_total_fs . ' == '. (float)round($datos->total_fs));
						$list[] = (array)$afi;
					}
				} 
				

		}
			Util::excel('componenetes_erroneos', 'hoja',$list);
			Session::flash('message', "Veificacion completada" . " BIEN:" . $found . " MAL:" . $nofound);
			return redirect('afi_observations');
	}
}
	

public function get_eco_com_diferencia2017_2018()
{
	global $result,$result1;

      $eco2018= DB::table('eco_com_applicants')
                    ->select(DB::raw("economic_complements.id,eco_com_applicants.identity_card as bene_ci, eco_com_applicants.first_name bene_nombre,eco_com_applicants.last_name as bene_paterno,eco_com_applicants.mothers_last_name as bene_materno, economic_complements.code as codigo, economic_complements.reception_date as fecha, economic_complements.year as ano, economic_complements.semester as semestre, economic_complements.total_rent as renta2018, economic_complements.aps_total_cc,economic_complements.aps_total_fsa, economic_complements.aps_total_fs,  economic_complements.aps_disability as renta_invalidez, affiliates.identity_card as afi_ci, affiliates.first_name as afi_nombre,affiliates.last_name as paterno, affiliates.mothers_last_name as materno, pension_entities.name as ente_gestor,eco_com_modalities.shortened as tipo_prestacion, eco_com_types.name as modalidad,economic_complements.total as total2018,degrees.shortened as grado,categories.percentage as categoria"))
                    ->leftJoin('economic_complements','eco_com_applicants.economic_complement_id','=','economic_complements.id')
                    ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                    ->leftJoin('eco_com_types','eco_com_modalities.eco_com_type_id','=','eco_com_types.id')
					->leftJoin('affiliates','economic_complements.affiliate_id','=','affiliates.id')
					->leftJoin('degrees', 'degrees.id', '=', 'economic_complements.degree_id')
					->leftJoin('categories', 'categories.id', '=', 'economic_complements.category_id')
                    ->leftJoin('pension_entities','affiliates.pension_entity_id','=','pension_entities.id')
                    ->where('pension_entities.id', '<>', 5)
                    ->where('economic_complements.eco_com_procedure_id', '=', 7)
                    ->where('economic_complements.total_rent', '>', 0)->get();

        foreach($eco2018 as $item2018) 
        {
            $eco2017= DB::table('eco_com_applicants')
                    ->select(DB::raw("economic_complements.id,eco_com_applicants.identity_card as bene_ci, eco_com_applicants.first_name bene_nombre,eco_com_applicants.last_name as bene_paterno,eco_com_applicants.mothers_last_name as bene_materno, economic_complements.code as codigo, economic_complements.reception_date as fecha, economic_complements.year as ano, economic_complements.semester as semestre, economic_complements.total_rent as renta2017, economic_complements.aps_total_cc,economic_complements.aps_total_fsa, economic_complements.aps_total_fs,  economic_complements.aps_disability as renta_invalidez, affiliates.identity_card as afi_ci, affiliates.first_name as afi_nombre,affiliates.last_name as paterno, affiliates.mothers_last_name as materno, pension_entities.name as ente_gestor, eco_com_types.name as modalidad,economic_complements.total as total2017"))
                    ->leftJoin('economic_complements','eco_com_applicants.economic_complement_id','=','economic_complements.id')
                    ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                    ->leftJoin('eco_com_types','eco_com_modalities.eco_com_type_id','=','eco_com_types.id')
                    ->leftJoin('affiliates','economic_complements.affiliate_id','=','affiliates.id')
                    ->leftJoin('pension_entities','affiliates.pension_entity_id','=','pension_entities.id')
                    ->where('pension_entities.id', '<>', 5)
                    ->where('economic_complements.eco_com_procedure_id', '=', 6)
                    ->where('economic_complements.total_rent', '>', 0)
                    ->where('eco_com_applicants.identity_card','=',rtrim($item2018->bene_ci))->first();
            if($eco2017)
            {                  
                    if ($item2018->total2018 < $eco2017->total2017)              {
						$result1[] = array("id" => $item2018->id,"bene_ci" => $item2018->bene_ci ,"bene_nombre" => $item2018->bene_nombre,"bene_paterno" => $item2018->bene_paterno,"bene_materno" => $item2018->bene_materno, "renta2017" => $eco2017->renta2017,"renta2018" => $item2018->renta2018,"total2017" =>$eco2017->total2017,"total2018" => $item2018->total2018,"grado" => $item2018->grado,"categoria" => $item2018->categoria,"tipo_prestacion" => $item2018->tipo_prestacion,"modalidad" => $item2018->modalidad );
                    }
                   
            }
                    
		}


	
       // dd($result);
       Util::excel('CE_diferencias2018y2017', 'hoja',(array)$result1);
	   Session::flash('message', "Diferencia de Total CE" . " BIEN:" . $found . " MAL:" . $nofound);
	   return redirect('afi_observations');
	}








}
