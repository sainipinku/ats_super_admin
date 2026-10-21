import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
    GoogleMap,
    InfoWindowF,
    MarkerClustererF,
    MarkerF,
    PolylineF,
    useJsApiLoader,
} from "@react-google-maps/api";
import {
    FaLayerGroup,
    FaMapMarkerAlt,
    FaMinus,
    FaPlus,
    FaRedoAlt,
    FaSearch,
    FaTimes,
} from "react-icons/fa";
import AuthenticatedLayout from "./Layouts/AuthenticatedLayout";

const mapContainerStyle = { width: "100%", height: "100%" };
const defaultCenter = { lat: 20.5937, lng: 78.9629 };
const mapOptions = {
    clickableIcons: false,
    fullscreenControl: false,
    mapTypeControl: false,
    streetViewControl: false,
    zoomControl: false,
    gestureHandling: "greedy",
};

function relativeTime(value) {
    if (!value) return "Time unavailable";

    const timestamp = new Date(value).getTime();
    if (Number.isNaN(timestamp)) return "Time unavailable";

    const seconds = Math.max(0, Math.floor((Date.now() - timestamp) / 1000));
    if (seconds < 60) return "just now";
    if (seconds < 3600) return `${Math.floor(seconds / 60)} min ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)} hr ago`;
    return `${Math.floor(seconds / 86400)} day ago`;
}

function initials(name = "") {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0] || "")
        .join("")
        .toUpperCase() || "?";
}

function escapeXml(value) {
    return String(value).replace(/[&<>"']/g, (character) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&apos;",
    })[character]);
}

function markerIcon(employee) {
    const photo = employee.profileImage
        ? `<image href="${escapeXml(employee.profileImage)}" x="4" y="4" width="44" height="44" clip-path="url(#avatar)"/>`
        : `<text x="26" y="32" text-anchor="middle" font-family="Arial,sans-serif" font-size="15" font-weight="700" fill="#fff">${escapeXml(initials(employee.name))}</text>`;
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 52 52"><defs><clipPath id="avatar"><circle cx="26" cy="26" r="22"/></clipPath></defs><circle cx="26" cy="26" r="25" fill="#fff" stroke="#4f46e5" stroke-width="2"/>${photo}<circle cx="26" cy="26" r="22" fill="none" stroke="#fff" stroke-width="2"/></svg>`;

    return {
        url: `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`,
        scaledSize: new window.google.maps.Size(42, 42),
        anchor: new window.google.maps.Point(21, 21),
    };
}

function statusColor(status) {
    if (status === "Recently updated") return "bg-emerald-500";
    if (status === "Stale") return "bg-amber-500";
    return "bg-slate-400";
}

function localDateTime(value) {
    return new Date(value.getTime() - value.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

export default function LocationTracking() {
    const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY || "";
    const { isLoaded, loadError } = useJsApiLoader({
        id: "cadmax-employee-location-map",
        googleMapsApiKey: apiKey,
    });
    const [map, setMap] = useState(null);
    const [employees, setEmployees] = useState([]);
    const [selectedEmployee, setSelectedEmployee] = useState(null);
    const [search, setSearch] = useState("");
    const [department, setDepartment] = useState("");
    const [status, setStatus] = useState("");
    const [mapType, setMapType] = useState("roadmap");
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [error, setError] = useState("");
    const [historyEmployee, setHistoryEmployee] = useState(null);
    const [historyPoints, setHistoryPoints] = useState([]);
    const [historyLoading, setHistoryLoading] = useState(false);
    const [historyError, setHistoryError] = useState("");
    const [historyFrom, setHistoryFrom] = useState(() => localDateTime(new Date(new Date().setHours(0, 0, 0, 0))));
    const [historyTo, setHistoryTo] = useState(() => localDateTime(new Date()));
    const [selectedHistoryPoint, setSelectedHistoryPoint] = useState(null);
    const [lastUpdated, setLastUpdated] = useState(null);
    const [refreshInterval, setRefreshInterval] = useState(60);
    const refreshTimer = useRef(null);
    const hasFitInitialLocations = useRef(false);
    const basePath = window.location.pathname.includes("/super/")
        ? window.location.pathname.slice(0, window.location.pathname.indexOf("/super/"))
        : "";

    const loadLocations = useCallback(async (silent = false) => {
        if (!silent) setLoading(true);
        else setRefreshing(true);
        setError("");

        try {
            const response = await fetch(`${basePath}/api/admin/employee-locations`, {
                credentials: "same-origin",
                headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
            });
            if (response.status === 401 || response.status === 403) {
                throw new Error("Your super-admin session has expired or is not authorized.");
            }
            if (!response.ok) throw new Error("Employee locations could not be loaded.");

            const payload = await response.json();
            if (!payload?.success || !Array.isArray(payload.data)) {
                throw new Error("The location service returned an unexpected response.");
            }

            setEmployees(payload.data);
            setLastUpdated(new Date());
            setRefreshInterval(Math.min(60, Math.max(30, Number(payload.reportingIntervalSeconds) || 60)));
        } catch (requestError) {
            setError(requestError.message || "Employee locations could not be loaded.");
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }, [basePath]);

    useEffect(() => {
        loadLocations();
    }, [loadLocations]);

    useEffect(() => {
        refreshTimer.current = window.setInterval(() => loadLocations(true), refreshInterval * 1000);
        return () => window.clearInterval(refreshTimer.current);
    }, [loadLocations, refreshInterval]);

    const departments = useMemo(
        () => [...new Set(employees.map((employee) => employee.department).filter(Boolean))].sort(),
        [employees],
    );

    const visibleEmployees = useMemo(() => {
        const query = search.trim().toLowerCase();
        return employees.filter((employee) => {
            const matchesSearch = !query
                || employee.name?.toLowerCase().includes(query)
                || employee.employeeId?.toLowerCase().includes(query);
            return matchesSearch
                && (!department || employee.department === department)
                && (!status || employee.status === status);
        });
    }, [employees, search, department, status]);

    const fitPoints = useCallback((points) => {
        if (!map || points.length === 0) return;
        if (points.length === 1) {
            map.panTo({ lat: Number(points[0].latitude), lng: Number(points[0].longitude) });
            map.setZoom(15);
            return;
        }

        const bounds = new window.google.maps.LatLngBounds();
        points.forEach((point) => bounds.extend({
            lat: Number(point.latitude),
            lng: Number(point.longitude),
        }));
        map.fitBounds(bounds, 72);
    }, [map]);

    const fitAll = useCallback(() => fitPoints(employees), [employees, fitPoints]);

    useEffect(() => {
        if (map && employees.length > 0 && !hasFitInitialLocations.current) {
            fitPoints(employees);
            hasFitInitialLocations.current = true;
        }
    }, [employees, fitPoints, map]);

    useEffect(() => {
        if (!map || typeof ResizeObserver === "undefined") return undefined;

        const observer = new ResizeObserver(() => {
            const center = map.getCenter();
            window.google.maps.event.trigger(map, "resize");
            if (center) map.setCenter(center);
        });
        observer.observe(map.getDiv());
        return () => observer.disconnect();
    }, [map]);

    const fetchHistory = useCallback(async (employee, from, to) => {
        setHistoryLoading(true);
        setHistoryError("");
        setSelectedHistoryPoint(null);

        try {
            const query = new URLSearchParams({ from, to });
            const response = await fetch(
                `${basePath}/api/admin/employees/${encodeURIComponent(employee.employeeId)}/location-history?${query}`,
                {
                    credentials: "same-origin",
                    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
                },
            );
            if (response.status === 401 || response.status === 403) {
                throw new Error("Your super-admin session has expired or is not authorized.");
            }
            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                throw new Error(payload?.message || "Location history could not be loaded.");
            }

            const payload = await response.json();
            if (!payload?.success || !Array.isArray(payload.data)) {
                throw new Error("The location service returned an unexpected response.");
            }
            setHistoryPoints(payload.data);
            window.setTimeout(() => fitPoints(payload.data), 0);
        } catch (requestError) {
            setHistoryError(requestError.message || "Location history could not be loaded.");
        } finally {
            setHistoryLoading(false);
        }
    }, [basePath, fitPoints]);

    const showHistory = (employee) => {
        const today = new Date();
        const startOfToday = new Date(today);
        startOfToday.setHours(0, 0, 0, 0);
        setHistoryEmployee(employee);
        setSelectedEmployee(null);
        setHistoryFrom(localDateTime(startOfToday));
        setHistoryTo(localDateTime(today));
        fetchHistory(employee, localDateTime(startOfToday), localDateTime(today));
    };

    const clearHistory = () => {
        setHistoryEmployee(null);
        setHistoryPoints([]);
        setSelectedHistoryPoint(null);
        setHistoryError("");
        window.setTimeout(() => fitPoints(employees), 0);
    };

    const historyCoordinates = useMemo(
        () => historyPoints.map((point) => ({
            lat: Number(point.latitude),
            lng: Number(point.longitude),
        })),
        [historyPoints],
    );

    return (
        <AuthenticatedLayout>
        <section className="relative h-full min-h-0 w-full overflow-hidden bg-slate-100">
            {isLoaded && (
                <GoogleMap
                    mapContainerStyle={mapContainerStyle}
                    center={defaultCenter}
                    zoom={5}
                    options={{ ...mapOptions, mapTypeId: mapType }}
                    onLoad={setMap}
                    onUnmount={() => setMap(null)}
                >
                    {!historyEmployee && (
                        <MarkerClustererF options={{ gridSize: 48, minimumClusterSize: 2 }}>
                            {(clusterer) => (
                                <>
                                    {visibleEmployees.map((employee) => {
                                        const position = {
                                            lat: Number(employee.latitude),
                                            lng: Number(employee.longitude),
                                        };
                                        return (
                                            <MarkerF
                                                key={employee.employeeId}
                                                position={position}
                                                clusterer={clusterer}
                                                icon={markerIcon(employee)}
                                                title={employee.name}
                                                label={{
                                                    text: `${employee.name} · ${relativeTime(employee.capturedAt)}`,
                                                    className: "employee-location-label",
                                                }}
                                                onClick={() => setSelectedEmployee(employee)}
                                            />
                                        );
                                    })}
                                </>
                            )}
                        </MarkerClustererF>
                    )}

                    {historyEmployee && historyPoints.length > 1 && (
                        <PolylineF
                            path={historyCoordinates}
                            options={{
                                geodesic: true,
                                strokeColor: "#4f46e5",
                                strokeOpacity: 0.85,
                                strokeWeight: 4,
                            }}
                        />
                    )}

                    {historyEmployee && historyPoints.map((point, index) => (
                        <MarkerF
                            key={`${point.capturedAt}-${index}`}
                            position={{ lat: Number(point.latitude), lng: Number(point.longitude) }}
                            label={{ text: String(index + 1), color: "#fff", fontWeight: "700" }}
                            icon={{
                                path: window.google.maps.SymbolPath.CIRCLE,
                                scale: 13,
                                fillColor: "#4f46e5",
                                fillOpacity: 1,
                                strokeColor: "#fff",
                                strokeWeight: 2,
                            }}
                            onClick={() => setSelectedHistoryPoint(point)}
                        />
                    ))}

                    {selectedEmployee && !historyEmployee && (
                        <InfoWindowF
                            position={{ lat: Number(selectedEmployee.latitude), lng: Number(selectedEmployee.longitude) }}
                            onCloseClick={() => setSelectedEmployee(null)}
                        >
                            <div className="w-[260px] p-1 text-slate-800">
                                <div className="flex items-center gap-3">
                                    <img
                                        src={selectedEmployee.profileImage || "/images/profileimg.png"}
                                        alt=""
                                        className="h-11 w-11 rounded-full border border-slate-200 object-cover"
                                        onError={(event) => { event.currentTarget.src = "/images/profileimg.png"; }}
                                    />
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-bold">{selectedEmployee.name}</p>
                                        <p className="text-xs text-slate-500">{selectedEmployee.employeeId}</p>
                                    </div>
                                </div>
                                <div className="mt-3 space-y-1.5 text-xs">
                                    <p><span className="font-semibold">Designation:</span> {selectedEmployee.designation || "Not specified"}</p>
                                    <p><span className="font-semibold">Department:</span> {selectedEmployee.department || "Not specified"}</p>
                                    <p><span className="font-semibold">Coordinates:</span> {Number(selectedEmployee.latitude).toFixed(6)}, {Number(selectedEmployee.longitude).toFixed(6)}</p>
                                    <p><span className="font-semibold">Updated:</span> {new Date(selectedEmployee.capturedAt).toLocaleString()}</p>
                                    <p><span className="font-semibold">Accuracy:</span> {selectedEmployee.accuracy == null ? "Not reported" : `${selectedEmployee.accuracy} m`}</p>
                                    <p className="flex items-center gap-1.5">
                                        <span className={`h-2 w-2 rounded-full ${statusColor(selectedEmployee.status)}`} />
                                        {selectedEmployee.status}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => showHistory(selectedEmployee)}
                                    className="mt-3 w-full rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-700"
                                >
                                    View location history
                                </button>
                            </div>
                        </InfoWindowF>
                    )}

                    {selectedHistoryPoint && historyEmployee && (
                        <InfoWindowF
                            position={{
                                lat: Number(selectedHistoryPoint.latitude),
                                lng: Number(selectedHistoryPoint.longitude),
                            }}
                            onCloseClick={() => setSelectedHistoryPoint(null)}
                        >
                            <div className="p-1 text-sm text-slate-800">
                                <p className="font-semibold">{historyEmployee.name}</p>
                                <p className="mt-1 text-xs">{new Date(selectedHistoryPoint.capturedAt).toLocaleString()}</p>
                                <p className="mt-1 text-xs">
                                    {Number(selectedHistoryPoint.latitude).toFixed(6)}, {Number(selectedHistoryPoint.longitude).toFixed(6)}
                                </p>
                            </div>
                        </InfoWindowF>
                    )}
                </GoogleMap>
            )}

            {(!isLoaded || loadError || !apiKey) && (
                <div className="absolute inset-0 flex items-center justify-center bg-slate-100 px-6 text-center">
                    <div className="max-w-md rounded-2xl border border-slate-200 bg-white/95 p-6 shadow-lg">
                        <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-600">
                            <FaLayerGroup />
                        </div>
                        <h2 className="text-sm font-bold text-slate-900">Map unavailable</h2>
                        <p className="mt-2 text-sm text-slate-600">
                            {!apiKey
                                ? "Configure VITE_GOOGLE_MAPS_API_KEY with a restricted browser key to display the employee map."
                                : loadError
                                    ? "Google Maps could not be loaded. Check the map key and network connection."
                                    : "Loading map…"}
                        </p>
                    </div>
                </div>
            )}

            <div className="pointer-events-none absolute inset-x-0 top-0 z-10 flex flex-wrap items-start justify-between gap-3 p-3 sm:p-4">
                <div className="pointer-events-auto w-full max-w-[min(100%,420px)] rounded-xl border border-slate-200 bg-white/95 p-2 shadow-lg backdrop-blur sm:w-auto">
                    {historyEmployee ? (
                        <div className="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                onClick={clearHistory}
                                className="flex h-9 items-center gap-2 rounded-lg px-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                            >
                                <FaTimes className="text-xs" /> Latest locations
                            </button>
                            <span className="hidden h-6 border-l border-slate-200 sm:block" />
                            <span className="max-w-[140px] truncate text-xs font-semibold text-slate-700">{historyEmployee.name}</span>
                            <input
                                aria-label="History start date"
                                type="datetime-local"
                                value={historyFrom}
                                max={historyTo}
                                onChange={(event) => setHistoryFrom(event.target.value)}
                                className="h-9 rounded-lg border-slate-200 py-1 text-xs"
                            />
                            <input
                                aria-label="History end date"
                                type="datetime-local"
                                value={historyTo}
                                min={historyFrom}
                                onChange={(event) => setHistoryTo(event.target.value)}
                                className="h-9 rounded-lg border-slate-200 py-1 text-xs"
                            />
                            <button
                                type="button"
                                onClick={() => fetchHistory(historyEmployee, historyFrom, historyTo)}
                                className="h-9 rounded-lg bg-indigo-600 px-3 text-xs font-semibold text-white hover:bg-indigo-700"
                            >
                                Show
                            </button>
                        </div>
                    ) : (
                        <div className="flex items-center gap-2">
                            <FaSearch className="ml-1 shrink-0 text-slate-400" />
                            <input
                                type="search"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search employee or ID"
                                aria-label="Search employee or employee ID"
                                className="h-9 min-w-0 flex-1 border-0 bg-transparent px-1 text-sm outline-none ring-0 placeholder:text-slate-400 focus:ring-0 sm:w-48"
                            />
                            <select
                                value={department}
                                onChange={(event) => setDepartment(event.target.value)}
                                aria-label="Filter by department"
                                className="h-9 max-w-32 rounded-lg border-slate-200 py-1 text-xs text-slate-600"
                            >
                                <option value="">All departments</option>
                                {departments.map((name) => <option key={name} value={name}>{name}</option>)}
                            </select>
                            <select
                                value={status}
                                onChange={(event) => setStatus(event.target.value)}
                                aria-label="Filter by reporting status"
                                className="hidden h-9 max-w-36 rounded-lg border-slate-200 py-1 text-xs text-slate-600 sm:block"
                            >
                                <option value="">All statuses</option>
                                <option>Recently updated</option>
                                <option>Stale</option>
                                <option>Offline / not reporting</option>
                            </select>
                        </div>
                    )}
                </div>

                <div className="pointer-events-auto flex flex-wrap items-center justify-end gap-1.5 rounded-xl border border-slate-200 bg-white/95 p-1.5 shadow-lg backdrop-blur">
                    <button
                        type="button"
                        aria-label="Fit all employees"
                        title="Fit all employees"
                        onClick={historyEmployee ? () => fitPoints(historyPoints) : fitAll}
                        className="flex h-9 items-center gap-2 rounded-lg px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                    >
                        <FaMapMarkerAlt /> <span className="hidden sm:inline">Fit all</span>
                    </button>
                    <button
                        type="button"
                        aria-label="Refresh employee locations"
                        title="Refresh employee locations"
                        onClick={() => loadLocations(true)}
                        disabled={refreshing}
                        className="flex h-9 w-9 items-center justify-center rounded-lg text-slate-700 hover:bg-slate-100 disabled:opacity-50"
                    >
                        <FaRedoAlt className={refreshing ? "animate-spin" : ""} />
                    </button>
                    <span className="mx-0.5 h-6 border-l border-slate-200" />
                    <button type="button" aria-label="Zoom in" onClick={() => map?.setZoom((map.getZoom() || 5) + 1)} className="h-9 w-9 rounded-lg text-slate-700 hover:bg-slate-100">
                        <FaPlus className="mx-auto" />
                    </button>
                    <button type="button" aria-label="Zoom out" onClick={() => map?.setZoom((map.getZoom() || 5) - 1)} className="h-9 w-9 rounded-lg text-slate-700 hover:bg-slate-100">
                        <FaMinus className="mx-auto" />
                    </button>
                    <button
                        type="button"
                        aria-label="Switch map layer"
                        title={mapType === "roadmap" ? "Switch to satellite" : "Switch to road map"}
                        onClick={() => setMapType((current) => current === "roadmap" ? "satellite" : "roadmap")}
                        className="h-9 w-9 rounded-lg text-slate-700 hover:bg-slate-100"
                    >
                        <FaLayerGroup className="mx-auto" />
                    </button>
                </div>
            </div>

            <div className="pointer-events-none absolute bottom-4 left-3 z-10 flex max-w-[calc(100%-1.5rem)] flex-wrap items-center gap-2 rounded-full border border-slate-200 bg-white/95 px-3 py-2 text-xs text-slate-600 shadow-md backdrop-blur sm:left-4">
                <span className="font-semibold text-slate-800">
                    {historyEmployee
                        ? `${historyPoints.length} history points`
                        : `${visibleEmployees.length} of ${employees.length} located`}
                </span>
                {historyLoading && <span className="text-indigo-600">Loading history…</span>}
                {loading && !historyEmployee && <span className="text-indigo-600">Loading locations…</span>}
                {lastUpdated && !historyEmployee && (
                    <span className="hidden border-l border-slate-200 pl-2 sm:inline">
                        Updated {lastUpdated.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })}
                    </span>
                )}
                {employees.length === 0 && !loading && !error && !historyEmployee && (
                    <span>No employee locations have been reported.</span>
                )}
                {historyEmployee && historyPoints.length === 0 && !historyLoading && !historyError && (
                    <span>No recorded points for this date range.</span>
                )}
                {(error || historyError) && <span className="text-rose-600">{error || historyError}</span>}
            </div>
            <style>{`
                .employee-location-label {
                    display: inline-block;
                    max-width: 150px;
                    overflow: hidden;
                    padding: 3px 7px;
                    border: 1px solid rgba(226, 232, 240, .95);
                    border-radius: 9999px;
                    background: rgba(255, 255, 255, .96);
                    color: #334155;
                    font: 600 10px/1.4 Arial, sans-serif;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                    transform: translateY(23px);
                    box-shadow: 0 1px 4px rgba(15, 23, 42, .16);
                }
            `}</style>
        </section>
        </AuthenticatedLayout>
    );
}
