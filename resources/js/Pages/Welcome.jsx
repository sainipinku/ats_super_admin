import { Head, Link } from '@inertiajs/react';
import Checkbox from "@/Components/Checkbox";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PrimaryButton from "@/Components/PrimaryButton";
import TextInput from "@/Components/TextInput";
import GuestLayout from "@/Layouts/GuestLayout";
import { useForm } from "@inertiajs/react";
import { useEffect,useState } from "react";
import { MdEmail } from "react-icons/md";
import { RiLockPasswordFill } from "react-icons/ri";
import { router } from '@inertiajs/react';
export default function Welcome({ auth, laravelVersion, phpVersion }) {
    const handleImageError = () => {
        document
            .getElementById('screenshot-container')
            ?.classList.add('!hidden');
        document.getElementById('docs-card')?.classList.add('!row-span-1');
        document
            .getElementById('docs-card-content')
            ?.classList.add('!flex-row');
        document.getElementById('background')?.classList.add('!hidden');
    };

    return (
        <GuestLayout>
            <Head title="Welcome" />
            <div className="mx-auto max-w-md rounded-lg bg-white p-8 shadow-lg dark:bg-gray-800">
                <h2 className="mb-6 text-center text-2xl font-bold text-gray-800 dark:text-white">
                    Login As
                </h2>
                <div className="space-y-4">
                    <Link
        href={route('super.login')}
        className="block w-full rounded-md bg-purple-600 px-4 py-2 text-center font-medium text-white transition hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2"
    >
        Super Admin
    </Link>
    <Link
        href={route('admin.login')}
        className="block w-full rounded-md bg-blue-600 px-4 py-2 text-center font-medium text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
    >
        Admin
    </Link>
    <Link
        href={route('doer.login')}
        className="block w-full rounded-md bg-green-600 px-4 py-2 text-center font-medium text-white transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
    >
        Doer
    </Link>
                </div>
            </div>
        </GuestLayout>
    );
}
