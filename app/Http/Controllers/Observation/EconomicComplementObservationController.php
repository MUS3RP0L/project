<?php

namespace Muserpol\Http\Controllers\Observation;

use Illuminate\Http\Request;
use Muserpol\EconomicComplementObservation;
use Muserpol\Http\Requests;
use Muserpol\Http\Controllers\Controller;
use Muserpol\ObservationType;

use Carbon\Carbon;
use Log;
use Datatables;
use Util;
use Auth;
class EconomicComplementObservationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
        // $observation = EconomicComplementObservation::where('id',$request->observation_id)->first();
        // return EconomicComplementObservation::find(2);
        if($request->observation_id=='')
        {
            $observation = new EconomicComplementObservation;
            $observation->date = Carbon::now();
        }
        else{
            $observation = EconomicComplementObservation::find($request->observation_id);
            
        }
        // return $observation;
        
        $observation->observation_type_id = $request->observation_type_id;
        $observation->message = $request->message;
        $observation->economic_complement_id = $request->economic_complement_id;
        $observation->user_id = Auth::user()->id;
        $observation->is_enabled = $request->has('is_enabled')?true:false;
        
        $observation->save();
        
        return back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *observationType
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Responsedestroy
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specobservationTypeified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
        return $id;
    }
    public function getComplementObservation(Request $request)
    {   
        Log::info($request->notes);
        if($request->notes==1)
        {
            $observations = EconomicComplementObservation::where('economic_complement_id',$request->economic_complement_id)
                                                        ->where('observation_type_id',11)  
                                                        ->get();
        }else{  

            $observations = EconomicComplementObservation::where('economic_complement_id',$request->economic_complement_id)
                                                        ->where('observation_type_id','<>',11)                                              
                                                        ->get();
        }   
        return Datatables::of($observations)
        ->editColumn('date',function ($observation)
        {
          return Util::getDateShort($observation->date);
        })
        ->addColumn('type',function ($observation){
          return $observation->observationType->name;
        })
        ->editColumn('is_enabled',function ($observation)
        {
          if ($observation->is_enabled) {
            return '<span class="label label-success">Subsanado</span>';
          }
          return '<span class="label label-danger">Vigente</span>';
        })
        ->addColumn('action', function ($observation) {
            $note = $observation->observation_type_id==11?'1':'0';
            $color = $observation->observation_type_id==11?'info':'danger';
          return
            '<div class="btn-group" style="margin:-3px 0;">
            <button type="button" class="btn btn-'.$color.' btn-raised btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fa fa-cog"></i> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <span class="caret"></span></button>
            <ul class="dropdown-menu">
                <li><a href="/print_observations/'.$observation->affiliate_id.'/'.$observation->observation_type_id.'"><i class="glyphicon glyphicon-print"></i> Imprimir</a></li>'.
                 ((Util::getRol()->module_id == $observation->observationType->module_id) ? '<li><a data-observation-id="'.$observation->id.'"  role="button" data-toggle="modal" data-target="#observationEditModal"  data-observation-type-id="'.$observation->observation_type_id.'" data-observation-message="'.$observation->message.'" data-observation-enabled="'.$observation->is_enabled.'" data-notes="'.$note.'" ><i class="fa fa-pencil" ></i> Editar</a></li>'.
                '<li><a data-toggle="modal" data-target="#observationDeleteModal" data-observation-id="'.$observation->id.'" data-observation-name="'.$observation->observationType->name.'"  class="deleteObservation" href="#"> <i class="fa fa-times-circle"></i> Eliminar</a></li>':'').'
              </ul>
            </div>';})
        ->make(true);
    }
    public function delete(Request $request)
    {
        $observation = EconomicComplementObservation::find($request->observation_id); 
        $observation->delete();
        return back();  
    }
}
