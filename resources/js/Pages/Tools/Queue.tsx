import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

type Scenario = 'stop_go' | 'merge' | 'reduced_lanes';

interface Input {
    scenario: Scenario;
    temp_speed_kmh: number;
    lanes_open: number;
    demand_vph: number;
    duration_hours: number;
    stop_seconds: number;
    site_length_m: number;
}

interface Result {
    capacity_per_lane_vph: number;
    total_capacity_vph: number;
    queue_vehicles: number;
    queue_length_m: number;
    queue_length_km: number;
    max_delay_s: number;
    site_traverse_s: number | null;
    advance_warning_required_m: number;
    notes: string[];
}

interface Props {
    scenarios: Scenario[];
    speeds: number[];
    input: Input;
    result: Result;
    guide: { url: string; label: string };
}

const SCENARIO_LABELS: Record<Scenario, string> = {
    stop_go: 'Stop / go',
    merge: 'Lane merge',
    reduced_lanes: 'Reduced lanes',
};

export default function QueueCalculator({ scenarios, speeds, input, result, guide }: Props) {
    const [form, setForm] = useState<Input>(input);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        router.get('/tools/queue', form as unknown as Record<string, string | number>, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const update = <K extends keyof Input>(k: K, v: Input[K]) => setForm({ ...form, [k]: v });

    const isStopGo = form.scenario === 'stop_go';

    return (
        <>
            <Head title="NZGTTM queue calculator" />
            <div className="mx-auto max-w-5xl px-4 py-8 md:px-6 md:py-10">
                <Link href="/" className="text-sm text-blue-600 hover:underline">
                    ← Back to map
                </Link>
                <h1 className="mt-4 text-2xl font-bold md:text-3xl">NZGTTM queue calculator</h1>
                <p className="mt-2 text-sm text-gray-700">
                    Estimates queue length and delay for a TTM site using the formulas in{' '}
                    <a href={guide.url} target="_blank" rel="noreferrer" className="text-blue-600 hover:underline">
                        {guide.label}
                    </a>
                    , pp.72–73. Results are first-principles estimates — verify with site
                    observation.
                </p>

                <form onSubmit={submit} className="mt-6 grid gap-5 md:grid-cols-2">
                    <fieldset className="rounded-lg border bg-white p-4 shadow-sm">
                        <legend className="px-2 text-sm font-semibold text-gray-700">Scenario</legend>
                        <div className="flex flex-wrap gap-2">
                            {scenarios.map((s) => (
                                <button
                                    key={s}
                                    type="button"
                                    onClick={() => update('scenario', s)}
                                    className={`min-h-[44px] rounded-md border px-3 py-2 text-sm font-medium ${
                                        s === form.scenario
                                            ? 'border-blue-600 bg-blue-600 text-white'
                                            : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                    }`}
                                >
                                    {SCENARIO_LABELS[s]}
                                </button>
                            ))}
                        </div>

                        <Field label="Temporary speed (km/h)" id="speed">
                            <select
                                id="speed"
                                value={form.temp_speed_kmh}
                                onChange={(e) => update('temp_speed_kmh', Number(e.target.value))}
                                className="min-h-[44px] w-full rounded border-gray-300"
                            >
                                {speeds.map((s) => (
                                    <option key={s} value={s}>
                                        {s} km/h
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Lanes open" id="lanes">
                            <input
                                id="lanes"
                                type="number"
                                min={1}
                                max={4}
                                value={form.lanes_open}
                                onChange={(e) => update('lanes_open', Number(e.target.value))}
                                className="min-h-[44px] w-full rounded border-gray-300"
                            />
                        </Field>
                    </fieldset>

                    <fieldset className="rounded-lg border bg-white p-4 shadow-sm">
                        <legend className="px-2 text-sm font-semibold text-gray-700">Traffic & site</legend>

                        <Field label="Demand (vph)" id="demand">
                            <input
                                id="demand"
                                type="number"
                                min={0}
                                value={form.demand_vph}
                                onChange={(e) => update('demand_vph', Number(e.target.value))}
                                className="min-h-[44px] w-full rounded border-gray-300"
                            />
                        </Field>

                        {isStopGo ? (
                            <Field label="Stop period (seconds)" id="stop">
                                <input
                                    id="stop"
                                    type="number"
                                    min={0}
                                    value={form.stop_seconds}
                                    onChange={(e) => update('stop_seconds', Number(e.target.value))}
                                    className="min-h-[44px] w-full rounded border-gray-300"
                                />
                            </Field>
                        ) : (
                            <Field label="Duration (hours)" id="duration">
                                <input
                                    id="duration"
                                    type="number"
                                    min={0}
                                    step={0.25}
                                    value={form.duration_hours}
                                    onChange={(e) => update('duration_hours', Number(e.target.value))}
                                    className="min-h-[44px] w-full rounded border-gray-300"
                                />
                            </Field>
                        )}

                        <Field label="Site length (m)" id="length">
                            <input
                                id="length"
                                type="number"
                                min={0}
                                value={form.site_length_m}
                                onChange={(e) => update('site_length_m', Number(e.target.value))}
                                className="min-h-[44px] w-full rounded border-gray-300"
                            />
                        </Field>
                    </fieldset>

                    <div className="md:col-span-2">
                        <button
                            type="submit"
                            className="min-h-[44px] rounded-md bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
                        >
                            Calculate
                        </button>
                    </div>
                </form>

                <section className="mt-8 grid gap-4 md:grid-cols-3">
                    <Stat
                        label="Per-lane capacity"
                        value={`${result.capacity_per_lane_vph.toLocaleString()} vph`}
                    />
                    <Stat
                        label="Total capacity"
                        value={`${result.total_capacity_vph.toLocaleString()} vph`}
                    />
                    <Stat
                        label="Site traverse"
                        value={result.site_traverse_s !== null ? `${result.site_traverse_s} s` : '—'}
                    />
                    <Stat
                        label="Queue length"
                        value={`${result.queue_length_m} m`}
                        sub={`${result.queue_length_km} km · ${result.queue_vehicles} veh`}
                        accent
                    />
                    <Stat
                        label="Max delay"
                        value={result.max_delay_s > 0 ? `${result.max_delay_s} s` : '—'}
                    />
                    <Stat
                        label="Advance warning ≥"
                        value={`${result.advance_warning_required_m} m`}
                        accent
                    />
                </section>

                {result.notes.length > 0 && (
                    <ul className="mt-4 space-y-2">
                        {result.notes.map((n, i) => (
                            <li
                                key={i}
                                className="rounded bg-amber-50 px-3 py-2 text-xs text-amber-900"
                            >
                                {n}
                            </li>
                        ))}
                    </ul>
                )}

                <p className="mt-4 text-xs text-gray-500">
                    Source: {guide.label} — Creative Commons Attribution 4.0.
                </p>
            </div>
        </>
    );
}

function Field({
    id,
    label,
    children,
}: {
    id: string;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <label htmlFor={id} className="mt-3 block">
            <span className="text-xs font-medium text-gray-600">{label}</span>
            <div className="mt-1">{children}</div>
        </label>
    );
}

function Stat({
    label,
    value,
    sub,
    accent,
}: {
    label: string;
    value: string;
    sub?: string;
    accent?: boolean;
}) {
    return (
        <div
            className={`rounded-lg border p-4 shadow-sm ${
                accent ? 'border-blue-200 bg-blue-50' : 'bg-white'
            }`}
        >
            <div className="text-xs uppercase tracking-wide text-gray-500">{label}</div>
            <div className="mt-1 font-mono text-xl font-semibold tabular-nums text-gray-900">
                {value}
            </div>
            {sub && <div className="mt-1 text-xs text-gray-500">{sub}</div>}
        </div>
    );
}
