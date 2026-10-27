import { Head, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Release = {
    version: string;
    content_html: string;
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
                            </CardHeader>
                            <CardContent>
                                <div
                                    className="min-w-0 text-sm leading-relaxed wrap-anywhere text-muted-foreground [&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4 [&_blockquote]:border-l-2 [&_blockquote]:pl-4 [&_h1]:text-base [&_h1]:font-medium [&_h2]:text-base [&_h2]:font-medium [&_h3]:font-medium [&_li+li]:mt-3 [&_ol]:list-decimal [&_ol]:pl-5 [&_p+p]:mt-3 [&_strong]:font-medium [&_strong]:text-foreground [&_ul]:list-disc [&_ul]:pl-5 [&>*+*]:mt-4"
                                    dangerouslySetInnerHTML={{ __html: release.content_html }}
                                />
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
