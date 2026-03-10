import { Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={
                'flex items-center gap-[10px] relative  pb-[10px]  ' +
                (active
                    ? 'customeBorder text-[#5246E6] font-[600] '
                    : ' text-[16px] font-[600] text-[#727272] hover:text-[#5246E6]') +
                className
            }
        >
            {children}
        </Link>
    );
}
