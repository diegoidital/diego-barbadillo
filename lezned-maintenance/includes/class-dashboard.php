<?php
/**
 * LZND_Dashboard — Admin visual dashboard.
 *
 * @package Lezned_Maintenance
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LZND_Dashboard {

    public static function init() {
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
        add_action( 'wp_ajax_lznd_get_dashboard_data', [ __CLASS__, 'ajax_data' ] );
    }

    public static function enqueue( $hook ) {
        if ( 'toplevel_page_lznd-menu' !== $hook ) {
            return;
        }
        wp_enqueue_script(
            'lznd-chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            [],
            '4.4.0',
            true
        );
    }

    public static function render() {
        $cur_year  = (int) current_time( 'Y' );
        $cur_month = (int) current_time( 'n' );
        $years     = range( $cur_year - 2, $cur_year + 1 );
        $meses     = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        $ajax_url = admin_url( 'admin-ajax.php' );
        $nonce    = wp_create_nonce( 'lznd_admin' );
        ?>
        <div class="wrap lznd-dashboard">
            <h1 class="lznd-dash__heading">
                <span class="dashicons dashicons-hammer" style="font-size:28px;height:28px;width:28px;margin-right:8px;vertical-align:middle;color:#1d5fa6"></span>
                Dashboard &mdash; Mantenimiento
            </h1>

            <div class="lznd-dash-filterbar">
                <div class="lznd-period-tabs">
                    <button class="lznd-period-tab" data-period="week">Semana</button>
                    <button class="lznd-period-tab" data-period="month">Mes</button>
                    <button class="lznd-period-tab" data-period="year">A&ntilde;o</button>
                    <button class="lznd-period-tab lznd-period-tab--active" data-period="all">Todo</button>
                </div>
                <div class="lznd-filter-selects">
                    <span class="lznd-filter-label" id="lznd-month-label">Mes:</span>
                    <select id="lznd-filter-month">
                        <?php foreach ( $meses as $n => $nombre ) : ?>
                            <option value="<?php echo $n; ?>" <?php selected( $n, $cur_month ); ?>><?php echo esc_html( $nombre ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="lznd-filter-label">A&ntilde;o:</span>
                    <select id="lznd-filter-year">
                        <?php foreach ( $years as $y ) : ?>
                            <option value="<?php echo $y; ?>" <?php selected( $y, $cur_year ); ?>><?php echo $y; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button id="lznd-dash-apply" class="button button-primary">Actualizar</button>
                </div>
            </div>

            <div class="lznd-charts-grid" id="lznd-charts-grid">

                <div class="lznd-chart-card lznd-chart-card--gauge" id="card-totales">
                    <h3 class="lznd-chart-card__title">Trabajos totales</h3>
                    <div class="lznd-gauge-wrap">
                        <canvas id="chart-totales"></canvas>
                        <div class="lznd-gauge-center">
                            <span class="lznd-gauge-value" id="gauge-totales-val">—</span>
                            <span class="lznd-gauge-sublabel">Total</span>
                        </div>
                    </div>
                    <div class="lznd-gauge-legend" id="gauge-totales-legend"></div>
                </div>

                <div class="lznd-chart-card lznd-chart-card--gauge" id="card-pendientes">
                    <h3 class="lznd-chart-card__title">Trabajos pendientes</h3>
                    <div class="lznd-gauge-wrap">
                        <canvas id="chart-pendientes"></canvas>
                        <div class="lznd-gauge-center">
                            <span class="lznd-gauge-value" id="gauge-pend-val">—</span>
                            <span class="lznd-gauge-sublabel">Pendiente</span>
                        </div>
                    </div>
                    <div class="lznd-gauge-legend" id="gauge-pend-legend"></div>
                </div>

                <div class="lznd-chart-card lznd-chart-card--timeline" id="card-proximos">
                    <h3 class="lznd-chart-card__title">Pr&oacute;ximos trabajos</h3>
                    <div id="lznd-timeline" class="lznd-timeline">
                        <p class="lznd-loading-text">Cargando...</p>
                    </div>
                </div>

                <div class="lznd-chart-card lznd-chart-card--clients" id="card-clientes">
                    <h3 class="lznd-chart-card__title">Clientes</h3>
                    <div class="lznd-table-scroll">
                        <table class="lznd-dash-table widefat striped">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody id="clientes-tbody">
                                <tr><td colspan="3" class="lznd-loading-text">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="lznd-chart-card lznd-chart-card--matrix" id="card-estado">
                    <h3 class="lznd-chart-card__title">Estado trabajos</h3>
                    <div id="lznd-matrix" class="lznd-matrix-wrap">
                        <p class="lznd-loading-text">Cargando...</p>
                    </div>
                </div>

                <div class="lznd-chart-card lznd-chart-card--stats" id="card-stats">
                    <h3 class="lznd-chart-card__title">Estad&iacute;sticas de trabajo</h3>
                    <div class="lznd-chart-canvas-wrap">
                        <canvas id="chart-stats"></canvas>
                    </div>
                </div>

            </div><!-- .lznd-charts-grid -->
        </div><!-- .lznd-dashboard -->

        <script>
        (function($){
            'use strict';

            var AJAX  = <?php echo json_encode( $ajax_url ); ?>;
            var NONCE = <?php echo json_encode( $nonce ); ?>;
            var charts = {};
            var period = 'all';

            // ── Period tabs ──────────────────────────────────────────────────

            $('.lznd-period-tab').on('click', function(){
                $('.lznd-period-tab').removeClass('lznd-period-tab--active');
                $(this).addClass('lznd-period-tab--active');
                period = $(this).data('period');
                if (period === 'month') {
                    $('#lznd-month-label').show();
                    $('#lznd-filter-month').show();
                    $('#lznd-filter-year').show();
                    $('#lznd-dash-apply').show();
                } else if (period === 'year') {
                    $('#lznd-month-label').hide();
                    $('#lznd-filter-month').hide();
                    $('#lznd-filter-year').show();
                    $('#lznd-dash-apply').show();
                } else {
                    $('#lznd-month-label').hide();
                    $('#lznd-filter-month').hide();
                    $('#lznd-filter-year').hide();
                    $('#lznd-dash-apply').hide();
                }
                if (period !== 'month' && period !== 'year') {
                    load();
                }
            });

            $('#lznd-dash-apply').on('click', function(){ load(); });

            // ── Load ─────────────────────────────────────────────────────────

            function load() {
                $('#lznd-charts-grid').css('opacity', 0.5);
                $.post(AJAX, {
                    action: 'lznd_get_dashboard_data',
                    nonce:  NONCE,
                    period: period,
                    year:   $('#lznd-filter-year').val(),
                    month:  $('#lznd-filter-month').val(),
                }, function(res){
                    $('#lznd-charts-grid').css('opacity', 1);
                    if (res && res.success) render(res.data);
                });
            }

            function render(d) {
                gaugeTotal(d);
                gaugePendiente(d);
                timeline(d.proximas || []);
                clientes(d.clientes || []);
                matrix(d.por_tipo || {}, d.estados || []);
                barStats(d.por_tipo || {});
            }

            // ── Gauge: Totales ────────────────────────────────────────────

            function gaugeTotal(d) {
                var total = d.totales || 0;
                var comp  = d.completados || 0;
                var pend  = d.pendientes  || 0;
                var curs  = d.en_curso    || 0;
                var rest  = Math.max(0, total - comp - pend - curs);

                $('#gauge-totales-val').text(total);

                if (charts.total) charts.total.destroy();
                var ctx = document.getElementById('chart-totales');
                if (!ctx) return;

                charts.total = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Completados','Pendientes','En curso','Otros'],
                        datasets:[{
                            data: [comp, pend, curs, total > 0 ? rest : 1],
                            backgroundColor: ['#22c55e','#f59e0b','#3b82f6','#e5e7eb'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        rotation: 270, circumference: 180, cutout: '72%',
                        plugins: { legend:{display:false}, tooltip:{enabled:true} },
                        animation: { duration: 600 }
                    }
                });

                $('#gauge-totales-legend').html(
                    leg('#22c55e','Completados',comp) +
                    leg('#f59e0b','Pendientes',pend) +
                    leg('#3b82f6','En curso',curs)
                );
            }

            // ── Gauge: Pendientes % ───────────────────────────────────────

            function gaugePendiente(d) {
                var total = d.totales || 0;
                var pend  = (d.pendientes||0) + (d.en_curso||0);
                var pct   = total > 0 ? Math.round((pend/total)*100) : 0;
                var color = pct < 30 ? '#22c55e' : pct < 70 ? '#f59e0b' : '#ef4444';

                $('#gauge-pend-val').text(pct + '%');

                if (charts.pend) charts.pend.destroy();
                var ctx = document.getElementById('chart-pendientes');
                if (!ctx) return;

                charts.pend = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        datasets:[{
                            data: [pct, 100-pct],
                            backgroundColor: [color, '#e5e7eb'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        rotation: 270, circumference: 180, cutout: '72%',
                        plugins: { legend:{display:false}, tooltip:{enabled:false} },
                        animation: { duration: 600 }
                    }
                });

                $('#gauge-pend-legend').html(
                    leg(color, 'Pendiente+En curso', pend + ' / ' + total)
                );
            }

            // ── Timeline ─────────────────────────────────────────────────

            function timeline(proximas) {
                var $tl = $('#lznd-timeline');
                if (!proximas.length) {
                    $tl.html('<p class="lznd-dash-empty">No hay pr&#243;ximas actuaciones.</p>');
                    return;
                }

                var today     = new Date();
                var lastDate  = new Date(proximas[proximas.length-1].fecha_prog);
                var spanDays  = Math.max(7, Math.ceil((lastDate - today) / 86400000) + 14);
                var colors    = {pendiente:'#f59e0b', en_curso:'#3b82f6', enviado:'#22c55e'};

                var html = '<div class="lznd-tl-head">'
                    + '<span>' + fmtDate(today) + '</span>'
                    + '<span class="lznd-tl-heading">TIMELINE</span>'
                    + '<span>' + fmtDate(lastDate) + '</span>'
                    + '</div><div class="lznd-tl-rows">';

                $.each(proximas, function(i, act){
                    var d    = new Date(act.fecha_prog);
                    var days = Math.max(0, (d - today) / 86400000);
                    var pct  = Math.min(92, Math.round((days / spanDays) * 100));
                    var col  = colors[act.estado] || '#94a3b8';
                    html += '<div class="lznd-tl-row">'
                        + '<div class="lznd-tl-label">' + esc(act.proyecto || act.titulo) + '</div>'
                        + '<div class="lznd-tl-track">'
                        + '<div class="lznd-tl-dot" style="left:' + pct + '%;background:' + col + '" title="' + esc(act.titulo) + '">'
                        + '<div class="lznd-tl-tip">' + fmtDate(d) + '</div>'
                        + '</div></div></div>';
                });
                html += '</div>';
                $tl.html(html);
            }

            // ── Clients table ─────────────────────────────────────────────

            function clientes(list) {
                var $tb = $('#clientes-tbody');
                if (!list.length) {
                    $tb.html('<tr><td colspan="3" class="lznd-dash-empty">Sin clientes.</td></tr>');
                    return;
                }
                var statusLabels = {pendiente:'Pendiente',en_curso:'En curso',enviado:'Enviado',cerrado:'Cerrado'};
                var statusClass  = {pendiente:'lznd-sbadge--orange',en_curso:'lznd-sbadge--blue',enviado:'lznd-sbadge--green',cerrado:'lznd-sbadge--gray'};
                var html = '';
                $.each(list, function(i,c){
                    var sc = statusClass[c.ultimo_estado] || 'lznd-sbadge--gray';
                    var sl = statusLabels[c.ultimo_estado] || (c.ultimo_estado || '—');
                    html += '<tr>'
                        + '<td><strong>' + esc(c.nombre) + '</strong><br><small>' + c.total + ' trabajos</small></td>'
                        + '<td><span class="lznd-sbadge ' + sc + '">' + sl + '</span></td>'
                        + '<td>' + (c.ultima_fecha || '—') + '</td>'
                        + '</tr>';
                });
                $tb.html(html);
            }

            // ── Status matrix ─────────────────────────────────────────────

            function matrix(porTipo, estados) {
                var $wrap = $('#lznd-matrix');
                var tipos = Object.keys(porTipo);
                if (!tipos.length) {
                    $wrap.html('<p class="lznd-dash-empty">Sin datos.</p>');
                    return;
                }

                var cols    = estados.length ? estados : ['pendiente','en_curso','enviado','cerrado'];
                var labels  = {pendiente:'Pendiente',en_curso:'En curso',enviado:'Enviado',cerrado:'Cerrado'};
                var bgcols  = {pendiente:'#f59e0b',en_curso:'#3b82f6',enviado:'#22c55e',cerrado:'#6b7280'};

                var html = '<table class="lznd-matrix-tbl"><thead><tr><th>Tipo</th>';
                $.each(cols, function(i,e){ html += '<th>' + (labels[e]||e) + '</th>'; });
                html += '</tr></thead><tbody>';
                $.each(porTipo, function(tipo, data){
                    html += '<tr><td class="lznd-matrix-tipo">' + esc(tipo) + '</td>';
                    $.each(cols, function(i,e){
                        var cnt = data[e] || 0;
                        var bg  = cnt > 0 ? bgcols[e] : '#f3f4f6';
                        var fg  = cnt > 0 ? '#fff'    : '#9ca3af';
                        html += '<td class="lznd-matrix-cell" style="background:' + bg + ';color:' + fg + '">' + cnt + '</td>';
                    });
                    html += '</tr>';
                });
                html += '</tbody></table>';
                $wrap.html(html);
            }

            // ── Bar chart: stats ──────────────────────────────────────────

            function barStats(porTipo) {
                var ctx = document.getElementById('chart-stats');
                if (!ctx) return;
                if (charts.stats) charts.stats.destroy();

                var tipos   = Object.keys(porTipo);
                var pending = tipos.map(function(t){ return (porTipo[t].pendiente||0)+(porTipo[t].en_curso||0); });
                var done    = tipos.map(function(t){ return (porTipo[t].enviado||0)+(porTipo[t].cerrado||0); });

                charts.stats = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: tipos,
                        datasets: [
                            { label:'Pendiente', data:pending, backgroundColor:'#93c5fd', borderRadius:4 },
                            { label:'Completado', data:done,   backgroundColor:'#4ade80', borderRadius:4 }
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend:{ position:'bottom' } },
                        scales: { y:{ beginAtZero:true, ticks:{ stepSize:1 } } }
                    }
                });
            }

            // ── Helpers ───────────────────────────────────────────────────

            function leg(color, label, val) {
                return '<span class="lznd-leg-dot" style="background:' + color + '"></span>'
                    + '<span class="lznd-leg-label">' + label + ': <strong>' + val + '</strong></span> ';
            }

            function fmtDate(d) {
                return ('0'+d.getDate()).slice(-2)+'/'+('0'+(d.getMonth()+1)).slice(-2)+'/'+d.getFullYear();
            }

            function esc(s) {
                return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }

            // Init: hide selectors (default period is 'all'), load when Chart.js is ready
            $('#lznd-month-label').hide();
            $('#lznd-filter-month').hide();
            $('#lznd-filter-year').hide();
            $('#lznd-dash-apply').hide();

            $(document).ready(function() {
                load();
            });

        })(jQuery);
        </script>
        <?php
    }

    /* ─── AJAX: Dashboard data ────────────────────────────────────────── */

    public static function ajax_data() {
        check_ajax_referer( 'lznd_admin', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Sin permiso.' ], 403 );
        }

        $period = sanitize_key( $_POST['period'] ?? 'month' );
        $year   = intval( $_POST['year']  ?? current_time( 'Y' ) );
        $month  = intval( $_POST['month'] ?? current_time( 'n' ) );

        // Date range
        switch ( $period ) {
            case 'week':
                $desde = date( 'Y-m-d', strtotime( 'monday this week' ) );
                $hasta = date( 'Y-m-d', strtotime( 'sunday this week' ) );
                break;
            case 'month':
                $desde = sprintf( '%04d-%02d-01', $year, $month );
                $hasta = date( 'Y-m-t', strtotime( $desde ) );
                break;
            case 'year':
                $desde = sprintf( '%04d-01-01', $year );
                $hasta = sprintf( '%04d-12-31', $year );
                break;
            default:
                $desde = '2000-01-01';
                $hasta = '2099-12-31';
                break;
        }

        // Actuaciones in period
        $mq = ( $period === 'all' )
            ? [ [ 'key' => '_lznd_estado', 'compare' => 'EXISTS' ] ]
            : [ [
                'key'     => '_lznd_fecha_prog',
                'value'   => [ $desde, $hasta . ' 23:59:59' ],
                'compare' => 'BETWEEN',
                'type'    => 'DATETIME',
            ] ];

        $acts = get_posts( [
            'post_type'      => 'lznd_actuacion',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => $mq,
        ] );

        $total       = count( $acts );
        $completados = 0;
        $pendientes  = 0;
        $en_curso    = 0;
        $por_tipo    = [];
        $estados_set = [];

        foreach ( $acts as $act ) {
            $estado   = get_post_meta( $act->ID, '_lznd_estado', true ) ?: 'pendiente';
            $tipo_tid = get_post_meta( $act->ID, '_lznd_tipo_term_id', true );
            $tipo_t   = $tipo_tid ? get_term( $tipo_tid, 'lznd_tipo' ) : null;
            $tipo_n   = ( $tipo_t && ! is_wp_error( $tipo_t ) ) ? $tipo_t->name : 'Sin tipo';

            if ( in_array( $estado, [ 'enviado', 'cerrado' ], true ) ) {
                $completados++;
            } elseif ( $estado === 'en_curso' ) {
                $en_curso++;
            } else {
                $pendientes++;
            }

            if ( ! isset( $por_tipo[ $tipo_n ] ) ) {
                $por_tipo[ $tipo_n ] = [];
            }
            $por_tipo[ $tipo_n ][ $estado ] = ( $por_tipo[ $tipo_n ][ $estado ] ?? 0 ) + 1;

            if ( ! in_array( $estado, $estados_set, true ) ) {
                $estados_set[] = $estado;
            }
        }

        // Upcoming actuaciones (next 90 days)
        $today  = current_time( 'Y-m-d' );
        $future = date( 'Y-m-d', strtotime( '+90 days' ) );

        $prox_posts = get_posts( [
            'post_type'      => 'lznd_actuacion',
            'post_status'    => 'publish',
            'posts_per_page' => 12,
            'orderby'        => 'meta_value',
            'meta_key'       => '_lznd_fecha_prog',
            'order'          => 'ASC',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_lznd_fecha_prog',
                    'value'   => [ $today, $future ],
                    'compare' => 'BETWEEN',
                    'type'    => 'DATE',
                ],
                [
                    'key'     => '_lznd_estado',
                    'value'   => [ 'pendiente', 'en_curso' ],
                    'compare' => 'IN',
                ],
            ],
        ] );

        $proximas = [];
        foreach ( $prox_posts as $p ) {
            $proy_id = get_post_meta( $p->ID, '_lznd_proyecto_id', true );
            $proy    = $proy_id ? get_post( $proy_id ) : null;
            $proximas[] = [
                'id'         => $p->ID,
                'titulo'     => $p->post_title,
                'proyecto'   => $proy ? $proy->post_title : '',
                'fecha_prog' => get_post_meta( $p->ID, '_lznd_fecha_prog', true ) ?: '',
                'estado'     => get_post_meta( $p->ID, '_lznd_estado', true ) ?: 'pendiente',
            ];
        }

        // Clients summary
        $cli_posts = get_posts( [
            'post_type'      => 'lznd_cliente',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $clientes = [];
        foreach ( $cli_posts as $cli ) {
            $proy_ids = get_posts( [
                'post_type'      => 'lznd_proyecto',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [ [ 'key' => '_lznd_cliente_id', 'value' => $cli->ID ] ],
            ] );

            $total_acts    = 0;
            $ultimo_estado = '';
            $ultima_fecha  = '';

            if ( ! empty( $proy_ids ) ) {
                $all_acts = get_posts( [
                    'post_type'      => 'lznd_actuacion',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'meta_query'     => [ [ 'key' => '_lznd_proyecto_id', 'value' => $proy_ids, 'compare' => 'IN' ] ],
                ] );
                $total_acts = count( $all_acts );

                $last = get_posts( [
                    'post_type'      => 'lznd_actuacion',
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'orderby'        => 'meta_value',
                    'meta_key'       => '_lznd_fecha_prog',
                    'order'          => 'DESC',
                    'meta_query'     => [ [ 'key' => '_lznd_proyecto_id', 'value' => $proy_ids, 'compare' => 'IN' ] ],
                ] );
                if ( ! empty( $last ) ) {
                    $ultimo_estado = get_post_meta( $last[0]->ID, '_lznd_estado', true ) ?: '';
                    $f             = get_post_meta( $last[0]->ID, '_lznd_fecha_prog', true );
                    $ultima_fecha  = $f ? date_i18n( 'd/m/Y', strtotime( $f ) ) : '';
                }
            }

            $clientes[] = [
                'nombre'        => $cli->post_title,
                'total'         => $total_acts,
                'ultimo_estado' => $ultimo_estado,
                'ultima_fecha'  => $ultima_fecha,
            ];
        }

        $order_e = [ 'pendiente', 'en_curso', 'enviado', 'cerrado' ];
        $estados = array_values( array_intersect( $order_e, $estados_set ) );

        wp_send_json_success( [
            'totales'    => $total,
            'completados'=> $completados,
            'pendientes' => $pendientes,
            'en_curso'   => $en_curso,
            'proximas'   => $proximas,
            'clientes'   => $clientes,
            'por_tipo'   => $por_tipo,
            'estados'    => $estados,
        ] );
    }
}

LZND_Dashboard::init();
