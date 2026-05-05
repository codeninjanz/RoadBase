<?php

namespace App\Http\Controllers;

use App\Support\Nzgttm;
use App\Support\NzgttmTable1;
use App\Support\QueueCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ToolsController extends Controller
{
    public function layout(Request $request): Response
    {
        $speed = (int) $request->integer('speed', 50);
        if (! in_array($speed, NzgttmTable1::SPEEDS, true)) {
            $speed = 50;
        }

        $surface = $request->string('surface', 'sealed')->toString();
        if (! in_array($surface, ['sealed', 'unsealed'], true)) {
            $surface = 'sealed';
        }

        return Inertia::render('Tools/Layout', [
            'speeds' => NzgttmTable1::SPEEDS,
            'surfaces' => ['sealed', 'unsealed'],
            'speed' => $speed,
            'surface' => $surface,
            'rows' => NzgttmTable1::lookup($speed, $surface),
            'guide' => [
                'url' => Nzgttm::GUIDE_URL,
                'label' => Nzgttm::GUIDE_LABEL,
            ],
        ]);
    }

    public function queue(Request $request): Response
    {
        $scenarios = ['stop_go', 'merge', 'reduced_lanes'];

        $scenario = $request->string('scenario', 'stop_go')->toString();
        if (! in_array($scenario, $scenarios, true)) {
            $scenario = 'stop_go';
        }

        $tempSpeed = (int) $request->integer('temp_speed_kmh', 30);
        if (! in_array($tempSpeed, NzgttmTable1::SPEEDS, true)) {
            $tempSpeed = 30;
        }

        $lanes = max(1, min(4, (int) $request->integer('lanes_open', 1)));
        $demand = max(0, (int) $request->integer('demand_vph', 1000));
        $duration = max(0.0, (float) $request->float('duration_hours', 1.0));
        $stopSeconds = max(0, (int) $request->integer('stop_seconds', 40));
        $siteLength = max(0, (int) $request->integer('site_length_m', 150));

        $input = [
            'scenario' => $scenario,
            'temp_speed_kmh' => $tempSpeed,
            'lanes_open' => $lanes,
            'demand_vph' => $demand,
            'duration_hours' => $duration,
            'stop_seconds' => $stopSeconds,
            'site_length_m' => $siteLength,
        ];

        return Inertia::render('Tools/Queue', [
            'scenarios' => $scenarios,
            'speeds' => NzgttmTable1::SPEEDS,
            'input' => $input,
            'result' => QueueCalculator::compute($input),
            'guide' => [
                'url' => Nzgttm::GUIDE_URL,
                'label' => Nzgttm::GUIDE_LABEL,
            ],
        ]);
    }
}
