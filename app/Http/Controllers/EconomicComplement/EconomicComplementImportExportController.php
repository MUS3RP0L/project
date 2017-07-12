<?php
namespace Muserpol\Http\Controllers\EconomicComplement;
use Illuminate\Http\Request;

use Muserpol\Http\Requests;
use Muserpol\Http\Controllers\Controller;
use Illuminate\Contracts\Filesystem\Factory;
use Storage;
use File;

use DB;
use Auth;
use Session;
use Carbon\Carbon;
use Muserpol\Helper\Util;
use Maatwebsite\Excel\Facades\Excel;

use Muserpol\Affiliate;
use Muserpol\EconomicComplement;

class EconomicComplementImportExportController extends Controller
{

    public function index()
    {

    }

    public static function import_from_senasir(Request $request)
    {
        if($request->hasFile('archive'))
        {
          global $year, $semester, $results,$i,$afi,$list;
          $reader = $request->file('archive');
          $filename = $reader->getRealPath();
          $year = $request->year;
          $semester = $request->semester;
          Excel::load($filename, function($reader) {
                  global $results,$i,$afi,$list;
                  ini_set('memory_limit', '-1');
                  ini_set('max_execution_time', '-1');
                  ini_set('max_input_time', '-1');
                  set_time_limit('-1');
                  $results = collect($reader->get());
          });

        //  return response()->json($results);
          $afi;
          $found=0;
          $nofound=0;
          foreach ($results as $datos) {
            
            $ext = ($datos->num_com ? "-".$datos->num_com : '');
            $ext = str_replace(' ','', $ext);                                  
            if($datos->renta == "DERECHOHABIENTE"){
              $comp = DB::table('eco_com_applicants') // VIUDEDAD
                  ->select(DB::raw('eco_com_applicants.identity_card as ci_app,economic_complements.*, eco_com_types.id as type'))
                  ->leftJoin('economic_complements','eco_com_applicants.economic_complement_id','=','economic_complements.id')
                  ->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')                                            
                  ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                  ->leftJoin('eco_com_types','eco_com_modalities.eco_com_type_id', '=', 'eco_com_types.id')
                  ->whereRaw("LTRIM(eco_com_applicants.identity_card,'0') ='".rtrim($datos->carnet.''.$ext)."'")                
                  ->where('eco_com_types.id','=', 2)
                  ->where('affiliates.pension_entity_id','=', 5)
                  ->whereYear('economic_complements.year', '=', $year)
                  ->where('economic_complements.semester', '=', $semester)->first();
            }
            elseif($datos->renta == "TITULAR")
            {
                $comp = DB::table('eco_com_applicants') // VEJEZ
                  ->select(DB::raw('eco_com_applicants.identity_card as ci_app,economic_complements.*, eco_com_types.id as type'))
                  ->leftJoin('economic_complements','eco_com_applicants.economic_complement_id','=','economic_complements.id')
                  ->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')                                            
                  ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                  ->leftJoin('eco_com_types','eco_com_modalities.eco_com_type_id', '=', 'eco_com_types.id')
                  ->whereRaw("LTRIM(eco_com_applicants.identity_card,'0') ='".rtrim($datos->carnet.''.$ext)."'")                
                  ->where('eco_com_types.id','=', 1)
                  ->where('affiliates.pension_entity_id','=', 5)
                  ->whereYear('economic_complements.year', '=', $year)
                  ->where('economic_complements.semester', '=', $semester)->first();
            }
            else
            {
                $comp = DB::table('eco_com_applicants') // ORFANDAD
                  ->select(DB::raw('eco_com_applicants.identity_card as ci_app,economic_complements.*, eco_com_types.id as type'))
                  ->leftJoin('economic_complements','eco_com_applicants.economic_complement_id','=','economic_complements.id')
                  ->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')                                            
                  ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                  ->leftJoin('eco_com_types','eco_com_modalities.eco_com_type_id', '=', 'eco_com_types.id')
                  ->whereRaw("LTRIM(eco_com_applicants.identity_card,'0') ='".rtrim($datos->carnet.''.$ext)."'")                
                  ->where('eco_com_types.id','=', 3)
                  ->where('affiliates.pension_entity_id','=', 5)
                  ->whereYear('economic_complements.year', '=', $year)
                  ->where('economic_complements.semester', '=', $semester)->first();
            }           
            if ($comp){
               
                $ecomplement = EconomicComplement::where('id','=', $comp->id)->first();
                if (is_null($ecomplement->total_rent))
                {
                  $reimbursements = $datos->reintegro_importe_adicional + $datos->reintegro_inc_gestion;
                  $discount = $datos->renta_dignidad + $datos->reintegro_renta_dignidad + $datos->reintegro_importe_adicional + $datos->reintegro_inc_gestion;
                  $total_rent = $datos->total_ganado - $discount;
                  
                  if($comp->type == 1 && $total_rent < 2000)  //Vejez Senasir
                  {
                    $ecomplement->eco_com_modality_id = 8;
                  } 
                  elseif ($comp->type == 2 && $total_rent < 2000) //Viudedad 
                  {  
                    $ecomplement->eco_com_modality_id = 9;
                  } 
                  elseif($comp->type == 3 && $total_rent < 2000) //Orfandad 
                  {  
                      $ecomplement->eco_com_modality_id = 12;
                  }
                  $ecomplement->sub_total_rent = $datos->total_ganado;                
                  $ecomplement->total_rent =  $total_rent;
                  $ecomplement->dignity_pension = $datos->renta_dignidad;
                  $ecomplement->reimbursement = $reimbursements;
                  $ecomplement->save();                
                  $found ++;
                }
                  
            }
            else{
              $nofound ++;
              $i ++;
              $list = $comp;
            }
          }          
              
          Session::flash('message', "Importación Exitosa"." F:".$found." NF:".$nofound);
          return redirect('economic_complement');
        }
        return back();
    }

    public static function import_from_aps(Request $request) {

      if($request->hasFile('archive'))
      {
        global $year, $semester, $results,$i,$afi,$list;
        $reader = $request->file('archive');
        $filename = $reader->getRealPath();
        $year = $request->year;
        $semester = $request->semester;
        Excel::load($filename, function($reader) {
                global $results,$i,$afi,$list;
                ini_set('memory_limit', '-1');
                ini_set('max_execution_time', '-1');
                ini_set('max_input_time', '-1');
                set_time_limit('-1');
                $results = collect($reader->get());
        });

        $afi;
        $found=0;
        $nofound=0;      
        foreach ($results as $datos)
        {   $ci = ltrim($datos->nro_identificacion, "0");
            
            $afi = DB::table('economic_complements')
                  ->select(DB::raw('affiliates.identity_card as ci_afi,economic_complements.*, eco_com_types.id as type'))     
                  ->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')                                 
                  ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id','=','eco_com_modalities.id')
                  ->leftJoin('eco_com_types','eco_com_modalities.eco_com_type_id', '=', 'eco_com_types.id')
                  ->whereRaw("LTRIM(affiliates.identity_card,'0') ='".$ci."'")         
                  ->where('affiliates.pension_entity_id','!=', 5)
                  ->whereYear('economic_complements.year', '=', $year)
                  ->where('economic_complements.semester', '=', $semester)->first();
           
              if ($afi)
              {                  
                  $comp1 = 0;
                  $comp2 = 0;
                  $comp3 = 0;
                  if ($datos->total_cc > 0) {
                      $comp1 = 1;
                  }
                  if ($datos->total_fsa > 0) {
                      $comp2 = 1;
                  }
                  if($datos->total_fs > 0) {
                      $comp3 = 1;
                  }
                  $comp = $comp1 + $comp2 + $comp3;
                  $ecomplement = EconomicComplement::where('id','=', $afi->id)->first();                                
                  $ecomplement->total_rent = $datos->total_pension;
                  $ecomplement->aps_total_cc = $datos->total_cc;
                  $ecomplement->aps_total_fsa = $datos->total_fsa;
                  $ecomplement->aps_total_fs = $datos->total_fs;

                  //Vejez
                  if ($afi->type == 1 )
                  {
                     if ($comp == 1 && $datos->total_pension >= 2000)
                     {
                        $ecomplement->eco_com_modality_id = 4;
                     }
                     elseif ($comp == 1 && $datos->total_pension < 2000)
                     {
                        $ecomplement->eco_com_modality_id = 6;
                     }
                     elseif ($comp > 1 && $datos->total_pension < 2000)
                     {
                        $ecomplement->eco_com_modality_id = 8;
                     }
                  }
                 //Viudedad
                  elseif ($afi->type == 2) 
                  {
                     if($comp == 1 && $datos->total_pension >= 2000) 
                     {
                         $ecomplement->eco_com_modality_id = 5;
                     } elseif ($comp == 1 && $datos->total_pension < 2000) 
                     {
                          $ecomplement->eco_com_modality_id = 7;
                     } elseif ($comp > 1 && $datos->total_pension < 2000 ) 
                     {
                         $ecomplement->eco_com_modality_id = 9;
                     }
                  }
                  else
                  { //ORFANDAD
                    if ($comp == 1 && $datos->total_pension >= 2000)
                     {
                        $ecomplement->eco_com_modality_id = 10;
                     }
                     elseif ($comp == 1 && $datos->total_pension < 2000)
                     {
                        $ecomplement->eco_com_modality_id = 11;
                     }
                     elseif ($comp > 1 && $datos->total_pension < 2000)
                     {
                        $ecomplement->eco_com_modality_id = 12;
                     }
                  }

                  $ecomplement->save();                  
                  $found ++;
              }
              else
              {
                $nofound ++;
                $i ++;
                $list[]= $datos;
              }
          }
          
          Session::flash('message', "Importación Exitosa"." F:".$found." NF:".$nofound);
          return redirect('economic_complement');
    }
  }

  public static function import_from_bank(Request $request) {
    //substr_replace($string ,"",-1);
    if($request->hasFile('archive'))
    {
      global $year, $semester, $results,$i,$afi,$list;
      $reader = $request->file('archive');
      $filename = $reader->getRealPath();
      $year = $request->year;
      $semester = $request->semester;
      Excel::load($filename, function($reader) {
              global $results,$i,$afi,$list;
              ini_set('memory_limit', '-1');
              ini_set('max_execution_time', '-1');
              ini_set('max_input_time', '-1');
              set_time_limit('-1');
              $results = collect($reader->get());
      });

      $afi;
      $found=0;
      $nofound=0;
      //return response()->json($results);
      foreach ($results as $valor){
          $ci = substr_replace($valor->carnet,"",-2);
          $card = rtrim($ci);
        //  return response()->json($card);
          $afi = DB::table('economic_complements')
            ->select(DB::raw('economic_complements.*'))
            ->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
            ->where('affiliates.identity_card', '=',rtrim($card))
            ->whereYear('economic_complements.review_date', '=', $year)
            ->where('economic_complements.semester', '=', $semester)
            ->where('economic_complements.eco_com_state_id', '=', 2)->first();
            if ($afi){
                $ecomplement = EconomicComplement::where('affiliate_id','=', $afi->affiliate_id)->whereYear('review_date','=', $afi->review_date)->where('semester','=', $afi->semester)->where('eco_com_state_id','=', $afi->eco_com_state_id)->first();
                $ecomplement->eco_com_state_id = 4;
                $ecomplement->total = $valor->monto_asignado;
                $ecomplement->payment_number = $valor->nro_comprobante;
                $ecomplement->payment_date = $valor->fecha_pago;
                $ecomplement->save();
                $found ++;
            }
            else{
              $nofound ++;
              $i ++;
              $list[]= $valor;
            }
        }
        //return response()->json($list);
        //export record no found
        Excel::create('REPORTE_BANCO_NO_ENCONTRADO', function($excel) {
            global $list, $j,$k;
            $j = 2;
            $k=1;
            $excel->sheet('Lista_Banco', function($sheet) {
            global $list, $j,$k;
            $sheet->row(1, array('NRO', 'DEPARTAMENTO','CARNET','NOMBRE','MONTO_ASIGNADO','DESCRIPCION1','DESCRIPCION2','NRO_COMPROBANTE','FECHA_PAGO'));
            foreach ($list as $datos) {
                $sheet->row($j, array($k,$datos->departamento,$datos->carnet,$datos->nombre, $datos->monto_asignado,$datos->descripcion1,$datos->descripcion2,$datos->nro_comprobante,$datos->fecha_pago));
                $j++;
                $k++;
            }

          });

        })->export('xlsx');
        Session::flash('message', "Importación Exitosa"." F:".$found." NF:".$nofound);
        return redirect('economic_complement');
  }

  }

    //############################################## EXPORT AFFILIATES TO APS ###################################
    public function export_to_aps(Request $request)
    {
        global $year, $semester,$i,$afi;
       $year = $request->year;
       $semester = $request->semester;
       Excel::create('Muserpol_para_aps', function($excel) {
                 global $year,$semester, $j;
                 $j = 2;
                 $excel->sheet("AFILIADOS_PARA_APS_".$year, function($sheet) {
                 global $year,$semester, $j, $i;
                 $i=1;
                 $sheet->row(1, array('NRO', 'TIPO_ID', 'NUM_ID', 'EXTENSION', 'CUA', 'PRIMER_APELLIDO_T', 'SEGUNDO_APELLIDO_T','PRIMER_NOMBRE_T','SEGUNDO_NOMBRE_T','APELLIDO_CASADA_T','FECHA_NACIMIENTO_T'));
                 $afi = DB::table('eco_com_applicants')
                     ->select(DB::raw('economic_complements.id,economic_complements.affiliate_id,affiliates.identity_card,cities.third_shortened,affiliates.nua,affiliates.last_name,affiliates.mothers_last_name,affiliates.first_name,affiliates.second_name,affiliates.surname_husband,affiliates.birth_date'))
                     ->leftJoin('economic_complements','eco_com_applicants.economic_complement_id','=','economic_complements.id')
                     ->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
                     ->leftJoin('cities', 'affiliates.city_identity_card_id', '=', 'cities.id')
                     ->where('affiliates.pension_entity_id','<>', 5)
                     ->whereYear('economic_complements.year', '=', $year)
                     ->where('economic_complements.semester', '=', $semester)->get();
                 foreach ($afi as $datos) {
                     $sheet->row($j, array($i, "I",Util::addcero($datos->identity_card,13),$datos->first_shortened,Util::addcero($datos->nua,9), $datos->last_name, $datos->mothers_last_name,$datos->first_name, $datos->second_name, $datos->surname_husband,Util::DateUnion($datos->birth_date)));
                     $j++;
                     $i++;
                 }
               });
           })->export('xlsx');
             Session::flash('message', "Importación Exitosa");
             return redirect('economic_complement');
    }

    public function export_to_bank(Request $request){
      global $year, $semester,$i,$afi,$semester1;
      $year = $request->year;
      $semester = $request->semester;
      $afi = DB::table('economic_complements')
          ->select(DB::raw('economic_complements.id,economic_complements.affiliate_id,economic_complements.semester,economic_complements.total,affiliates.identity_card,cities.shortened as ext,affiliates.last_name,affiliates.mothers_last_name,affiliates.first_name,affiliates.second_name,affiliates.surname_husband,eco_com_modalities.name,degrees.shortened as degree'))
          ->leftJoin('affiliates', 'economic_complements.affiliate_id', '=', 'affiliates.id')
          ->leftJoin('eco_com_modalities','economic_complements.eco_com_modality_id', '=', 'eco_com_modalities.id')
          //->join('cities', 'affiliates.city_id', '=', 'cities.id')
          ->leftJoin('cities', 'affiliates.city_identity_card_id', '=', 'cities.id')
          ->leftJoin('degrees', 'affiliates.degree_id', '=', 'degrees.id')
          ->whereYear('economic_complements.review_date', '=', $year)
          ->where('economic_complements.semester', '=', $semester)
          ->where('economic_complements.eco_com_state_id', '=', 2)->get();
      //return response()->json($afi);

      if($afi){
            if($semester == "F"){
              $semester1 = "MUSERPOL PAGO COMPLEMENTO ECONOMICO 1ER SEM ".$year;
              $abv ="Pago_Banco_Union_1ER_SEM_".$year;
            }
            else{
              $semester1 = "MUSERPOL PAGO COMPLEMENTO ECONOMICO 2DO SEM ".$year;
              $abv ="Pago_Banco_Union_2DO_SEM_".$year;
            }
            Excel::create($abv, function($excel) {
                global $year,$semester,$afi,$j,$semester1;
                $j = 2;
                $excel->sheet("AFILIADOS_PARA_APS_".$year, function($sheet) {
                global $year,$semester, $afi,$j, $i,$semester1;
                $i=1;
                $sheet->row(1, array('NRO', 'DEPARTAMENTO','IDENTIFICACION','NOMBRE_Y_APELLIDO','IMPORTE_A_PAGAR','MONEDA_DEL_IMPORTE','DESCRIPCION1','DESCRIPCION2','DESCRIPCION3'));
                foreach ($afi as $datos) {
                    $economic =  EconomicComplement::idIs($datos->id)->first();
                    $sheet->row($j, array($i,$economic->city->second_shortened,$datos->identity_card." ".$datos->ext,$datos->first_name." ".$datos->second_name." ".$datos->last_name." ".$datos->mothers_last_name." ".$datos->surname_husband, $datos->total,"1",$datos->name,$datos->degree,$semester1));
                    $j++;
                    $i++;
                }
              });
          })->export('xlsx');
          return redirect('economic_complement');
          Session::flash('message', "Importación Exitosa");

      }
      else {

        Session::flash('message', "No existen registros para exportar");
        return redirect('economic_complement');

      }


    }
    public function create()
    {
        //
    }


    public function store(Request $request)
    {
        //
    }


    public function show($id)
    {
        //
    }


    public function edit($id)
    {
        //
    }


    public function update(Request $request, $id)
    {
        //
    }


    public function destroy($id)
    {
        //
    }
}
