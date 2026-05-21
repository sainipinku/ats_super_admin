import NavLink from "@/Components/NavLink";

export default function UserDropdown({ open }) {
    if (!open) return null;

    return (
        <div className="absolute right-0 mt-2 w-48 cards border rounded shadow z-50">
            <ul className="py-1">
                <li>
                    <NavLink
                        href={route("callingteam.logout")}
                        method="post"
                        as="button"
                        className="hover:bg-gray-100 cursor-pointer px-4 py-2 text-[#999] dark:text-white"
                    >
                        Logout
                    </NavLink>
                </li>
            </ul>
        </div>
    );
}
