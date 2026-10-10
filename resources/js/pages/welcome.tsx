import { Head } from '@inertiajs/react';

export default function Welcome() {
    return (
        <>
            <Head>
                <title>Welcome</title>
            </Head>

            <div>
                <h1 className="text-lg font-bold text-blue-500">Hello World! from fresh app</h1>
            </div>
        </>
    );
}
