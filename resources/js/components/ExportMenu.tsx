import { useState } from 'react';
import type { SiteDetail } from '@/types';

interface Props {
    site: SiteDetail;
}

export function ExportMenu({ site }: Props) {
    const [copied, setCopied] = useState(false);

    const copyText = async () => {
        const r = await fetch(`/api/sites/${site.id}/tmp.txt`);
        if (!r.ok) return;
        const text = await r.text();
        await navigator.clipboard.writeText(text);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="space-y-2">
            <h3 className="text-sm font-semibold">Export for TMP</h3>
            <div className="flex flex-wrap gap-2">
                <button
                    onClick={copyText}
                    className="rounded bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
                >
                    {copied ? 'Copied!' : 'Copy to clipboard'}
                </button>
                <a
                    href={`/api/sites/${site.id}/tmp.pdf`}
                    className="rounded border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Download PDF
                </a>
            </div>
            <p className="text-xs text-gray-500">
                Always verify figures against the originating dataset before submitting a TMP.
            </p>
        </div>
    );
}
