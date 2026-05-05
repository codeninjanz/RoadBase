import { useState } from 'react';
import type { SiteDetail } from '@/types';

interface Props {
    site: SiteDetail;
}

export function ExportMenu({ site }: Props) {
    const [copied, setCopied] = useState(false);

    const copyText = async () => {
        const r = await fetch(`/api/sites/${site.id}/context.txt`);
        if (!r.ok) return;
        const text = await r.text();
        await navigator.clipboard.writeText(text);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    };

    const mobileRoadUrl = `https://mobileroad.org/?lat=${site.lat}&lon=${site.lng}`;

    return (
        <div className="space-y-3">
            <h3 className="text-sm font-semibold">Activity &amp; environment context</h3>
            <p className="text-xs text-gray-500">
                Context input to your NZGTTM 2023 risk assessment — not a TMP.
            </p>
            <div className="flex flex-wrap gap-2">
                <button
                    onClick={copyText}
                    className="min-h-[44px] rounded bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >
                    {copied ? 'Copied!' : 'Copy text'}
                </button>
                <a
                    href={`/api/sites/${site.id}/context.pdf`}
                    className="min-h-[44px] rounded border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Download PDF
                </a>
                <a
                    href={mobileRoadUrl}
                    target="_blank"
                    rel="noreferrer"
                    className="min-h-[44px] rounded border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Open in Mobile Road ↗
                </a>
            </div>
        </div>
    );
}
