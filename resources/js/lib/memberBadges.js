/**
 * Utility functions for extracting, deduplicating, and formatting
 * multiple role badges and labels for team members across forms and select dropdowns.
 */

export function getMemberRoleBadges(memberOrItem) {
    if (!memberOrItem) return [];

    const member = memberOrItem.member || memberOrItem;
    const badges = [];

    // 1. Direct role relation on team pivot (e.g. project team member assignment)
    if (memberOrItem.role?.name) {
        badges.push(memberOrItem.role.name);
    }
    if (memberOrItem.role_name) {
        badges.push(memberOrItem.role_name);
    }

    // 2. Pre-computed arrays from backend
    if (Array.isArray(member.role_badges) && member.role_badges.length > 0) {
        badges.push(...member.role_badges);
    }
    if (Array.isArray(member.roles_list) && member.roles_list.length > 0) {
        badges.push(...member.roles_list);
    }

    // 3. Formatted bracket string from backend (e.g. "[Manager (Survey)] [Surveyor]")
    if (typeof member.roles_formatted === "string" && member.roles_formatted.trim() !== "") {
        const matches = member.roles_formatted.match(/\[(.*?)\]/g);
        if (matches) {
            matches.forEach((m) => badges.push(m.replace(/^\[|\]$/g, "").trim()));
        }
    }

    // 4. Comma-separated role_badge (roles only, strictly exclude designations)
    if (typeof member.role_badge === "string" && member.role_badge.trim() !== "") {
        member.role_badge.split(",").forEach((s) => badges.push(s.trim()));
    }

    // 5. If member has roles array of objects { name: '...' }
    if (Array.isArray(member.roles)) {
        member.roles.forEach((r) => {
            if (typeof r === "object" && r?.name) {
                badges.push(r.name);
            }
        });
    }

    // 6. Super Admin / Admin boolean flags
    if (member.is_super_admin) {
        badges.unshift("Super Admin");
    } else if (member.is_admin) {
        badges.unshift("Admin");
    }

    // Deduplicate case-insensitively & filter out empty or generic "Member" / "Team Member"
    let uniqueBadges = [];
    const seen = new Set();
    for (const b of badges) {
        if (!b || typeof b !== "string") continue;
        const cleaned = b.trim();
        if (!cleaned || cleaned.toLowerCase() === "member" || cleaned.toLowerCase() === "team member") continue;
        const norm = cleaned.toLowerCase().replace(/[^a-z0-9]/g, "");
        if (!seen.has(norm)) {
            seen.add(norm);
            uniqueBadges.push(cleaned);
        }
    }

    // Deduplicate aliases: if Vehicle Driver exists, remove generic Driver
    if (uniqueBadges.some((b) => /vehicle driver/i.test(b))) {
        uniqueBadges = uniqueBadges.filter((b) => !/^driver$/i.test(b.trim()));
    }
    // If Surveyor exists, remove Survey Man
    if (uniqueBadges.some((b) => /^surveyor$/i.test(b.trim()))) {
        uniqueBadges = uniqueBadges.filter((b) => !/survey man/i.test(b));
    }
    // If Draft Person exists, remove Draft Man
    if (uniqueBadges.some((b) => /^draft person$/i.test(b.trim()))) {
        uniqueBadges = uniqueBadges.filter((b) => !/draft man/i.test(b));
    }

    return uniqueBadges;
}

export function formatMemberOptionLabel(memberOrItem, { extra = "" } = {}) {
    if (!memberOrItem) return "";
    const member = memberOrItem.member || memberOrItem;
    const name = member.name || (memberOrItem.member_id ? `Member #${memberOrItem.member_id}` : "");
    const badges = getMemberRoleBadges(memberOrItem);
    const badgeStr = badges.length > 0 ? ` [${badges.join("] [")}]` : "";
    const extraStr = extra ? ` • ${extra}` : "";
    return `${name}${badgeStr}${extraStr}`;
}

export function formatMemberSelectOption(memberOrItem, { extra = "", placeholder = "" } = {}) {
    if (!memberOrItem) {
        return { value: "", label: placeholder, badges: [] };
    }
    const member = memberOrItem.member || memberOrItem;
    const id = member.id ?? memberOrItem.member_id ?? memberOrItem.id ?? "";
    const badges = getMemberRoleBadges(memberOrItem);
    return {
        value: String(id),
        label: formatMemberOptionLabel(memberOrItem, { extra }),
        badges,
    };
}
