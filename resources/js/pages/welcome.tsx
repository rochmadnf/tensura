import { Head } from '@inertiajs/react';
import { useState } from 'react';

export default function Welcome() {
    const [test, setTest] = useState<boolean>(false);
    return (
        <>
            <Head>
                <title>Welcome</title>
            </Head>

            <div>
                {test ? (
                    <h1 className="text-lg font-bold text-blue-500">Hello World! from fresh app</h1>
                ) : (
                    <p>test: false</p>
                )}
                <button onClick={() => setTest(!test)} className="rounded-md bg-blue-500 px-4 py-2">
                    Klik Me
                </button>
            </div>
        </>
    );
}

Welcome.layout = {
    pageName: 'Dashboard',
};
