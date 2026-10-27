import { Head, usePage } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type Release = {
    version: string;
    summary: string;
    features: { title: string; description: string }[];
    notes: string[];
};

const pageName = 'Tentang Aplikasi';

export default function About({ releases }: { releases: Release[] }) {
    const { app } = usePage().props;

    return (
        <>
            <Head title={pageName}>
                <meta name="description" content={`Informasi versi dan catatan rilis ${app.name}`} />
            </Head>
            <div className="mx-auto flex w-full max-w-4xl min-w-0 flex-col gap-6">
                <section aria-label="Informasi aplikasi" className="flex flex-col gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">{app.name}</h1>
                    <p className="text-sm text-muted-foreground">
                        Versi berjalan <span className="font-medium text-foreground">v{app.version}</span>
                    </p>
                    <p className="text-sm leading-relaxed">
                        Aplikasi untuk mengelola data pendukung dan pembuatan surat berbasis templat DOCX.
                    </p>
                </section>
                <section aria-labelledby="release-notes-title" className="flex min-w-0 flex-col gap-4">
                    <h2 id="release-notes-title" className="text-lg font-semibold">
                        Catatan Rilis
                    </h2>
                    {releases.map((release) => (
                        <Card key={release.version}>
                            <CardHeader>
                                <CardTitle>
                                    <h3>v{release.version}</h3>
                                </CardTitle>
                                <CardDescription>{release.summary}</CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-6">
                                <dl className="divide-y">
                                    {release.features.map((feature) => (
                                        <div
                                            key={feature.title}
                                            className="flex flex-col gap-1 py-4 first:pt-0 last:pb-0 sm:flex-row sm:gap-6"
                                        >
                                            <dt className="text-sm font-medium sm:w-44 sm:shrink-0">{feature.title}</dt>
                                            <dd className="min-w-0 text-sm leading-relaxed text-muted-foreground">
                                                {feature.description}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                {release.notes.map((note) => (
                                    <p key={note} className="text-sm leading-relaxed text-muted-foreground">
                                        {note}
                                    </p>
                                ))}
                            </CardContent>
                        </Card>
                    ))}
                    {releases.length === 0 && (
                        <p className="text-sm text-muted-foreground">Catatan rilis belum tersedia.</p>
                    )}
                </section>
            </div>
        </>
    );
}

About.layout = { pageName };
