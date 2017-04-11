<aside class="main-sidebar">

    <section class="sidebar">
        <ul class="sidebar-menu">
            <li class="header">MENÚ</li>

            <li {!! (Request::is('home') ? 'class=active' : '') !!}>
                <a href="{!! url('home') !!}"><i class='fa fa-fw fa-tachometer' aria-hidden="true"></i>&nbsp; <span>Inicio</span></a>
            </li>

            <li {!! (Request::is('affiliate') ? 'class=active' : '') !!}>
                <a href="{!! url('affiliate') !!}"><i class='fa fa-fw fa-male fa-lg' aria-hidden="true"></i>&nbsp; <span>Afiliados</span></a>
            </li>


            <li {!! (Request::is('direct_contribution') ? 'class=active' : '') !!}>
                <a href="{!! url('direct_contribution') !!}"><i class='fa fa-fw fa-arrow-circle-down fa-lg' aria-hidden="true"></i>&nbsp; <span>Aportes Directos</span></a>
            </li>

            <li {!! (Request::is('loan') ? 'class=active' : '') !!}>
                <a href="#"><i class="fa fa-fw fa-money fa-lg" aria-hidden="true"></i>&nbsp; <span>Préstamos</span></a>
            </li>

            <li {!! (Request::is('retirement_fund') ? 'class=active' : '') !!}>
                <a href="#">&nbsp;<i class="glyphicon glyphicon-piggy-bank" aria-hidden="true"></i>&nbsp; <span>Fondo de Retiro</span></a>
            </li>
            
            <li {!! (Request::is('economic_complement') ? 'class=active' : '') !!}>
                <a href="{!! url('economic_complement') !!}"><i class="fa fa-fw fa-puzzle-piece fa-lg" aria-hidden="true"></i>&nbsp; <span>Complemento Económico</span></a>
            </li>
            {{-- @if(Auth::user()->role_id==7 || Auth::user()->role_id==8)
              <li {!! (Request::is('economic_complement') ? 'class=active' : '') !!}>
                  <a href="{!! url('economic_complement') !!}"><i class="fa fa-fw fa-puzzle-piece fa-lg" aria-hidden="true"></i>&nbsp; <span>Complemento Económico</span></a>
              </li>
            @endif --}}

            <li {!! (Request::is('mortuary') ? 'class=active' : '') !!}>
                <a href="#"><i class="fa fa-fw fa-heartbeat fa-lg" aria-hidden="true"></i>&nbsp; <span>Cuota, Auxilio Mortuorio</span></a>
            </li>

            <li {!! (Request::is('budget') ? 'class=active' : '') !!}>
                <a href="#"><i class="fa fa-fw fa-edit fa-lg" aria-hidden="true"></i>&nbsp; <span>Presupuesto</a>
            </li>

            <li {!! (Request::is('accounting') ? 'class=active' : '') !!}>
                <a href="#"><i class="fa fa-fw fa-book fa-lg" aria-hidden="true"></i>&nbsp; <span>Contabilidad</a>
            </li>

            <li {!! (Request::is('voucher') ? 'class=active' : '') !!}>
                <a href="{!! url('voucher') !!}"><i class="fa fa-fw fa-usd fa-lg" aria-hidden="true"></i>&nbsp; <span>Tesorería</a>
            </li>

        </ul>
    </section>

</aside>
