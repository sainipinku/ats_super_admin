import Modal from "@/Components/Modal";

export default function VehicleViewModal({
    isOpen,
    onClose,
    viewVehicle,
    getStatusDisplay,
    getInsuranceStatusDisplay,
    getPucStatusDisplay,
    sectionCardClass,
    sectionTitleClass,
}) {
    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="4xl" topCloseButton={true} handleTopClose={onClose}>
            <div className="p-2 md:p-4 dark:bg-[#080626]">
                <h2 className="text-xl font-bold mb-6 dark:text-white">Vehicle Details</h2>

                {viewVehicle && (
                    <div className="space-y-5">
                        {/* Vehicle Image */}
                        <div className="flex justify-center mb-6">
                            <img src={viewVehicle.vehicle_image_url} alt={viewVehicle.vehicle_name} className="w-32 h-32 rounded-xl object-cover border-2 border-gray-200 dark:border-gray-700" />
                        </div>

                        {/* Basic Information */}
                        <div className={sectionCardClass}>
                            <h3 className={sectionTitleClass}>Basic Information</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Vehicle ID</label><p className="text-sm font-medium dark:text-white">{viewVehicle.vehicle_id}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Vehicle Type</label><p className="text-sm font-medium dark:text-white">{viewVehicle.vehicle_type || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Vehicle Number</label><p className="text-sm font-medium dark:text-white">{viewVehicle.vehicle_number}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Vehicle Name</label><p className="text-sm font-medium dark:text-white">{viewVehicle.vehicle_name || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Brand</label><p className="text-sm font-medium dark:text-white">{viewVehicle.brand || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Fuel Type</label><p className="text-sm font-medium dark:text-white">{viewVehicle.fuel_type || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Color</label><p className="text-sm font-medium dark:text-white">{viewVehicle.color || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Manufacturing Year</label><p className="text-sm font-medium dark:text-white">{viewVehicle.manufacturing_year || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Engine Number</label><p className="text-sm font-medium dark:text-white">{viewVehicle.engine_number || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Chassis Number</label><p className="text-sm font-medium dark:text-white">{viewVehicle.chassis_number || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Purchase Date</label><p className="text-sm font-medium dark:text-white">{viewVehicle.purchase_date || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Purchase Amount</label><p className="text-sm font-medium dark:text-white">{viewVehicle.purchase_amount ? '₹ ' + parseFloat(viewVehicle.purchase_amount).toLocaleString() : '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Current KM Reading</label><p className="text-sm font-medium dark:text-white">{viewVehicle.current_km_reading || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Status</label>
                                    <p className="text-sm font-medium">
                                        <span className={`px-2 py-0.5 rounded-full text-xs ${getStatusDisplay(viewVehicle.status).class}`}>
                                            {getStatusDisplay(viewVehicle.status).text}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Insurance Details */}
                        <div className={sectionCardClass}>
                            <h3 className={sectionTitleClass}>Insurance Details</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Provider</label><p className="text-sm font-medium dark:text-white">{viewVehicle.insurance_provider || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Policy Number</label><p className="text-sm font-medium dark:text-white">{viewVehicle.policy_number || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Type</label><p className="text-sm font-medium dark:text-white">{viewVehicle.insurance_type || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Start Date</label><p className="text-sm font-medium dark:text-white">{viewVehicle.insurance_start_date || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">End Date</label><p className="text-sm font-medium dark:text-white">{viewVehicle.insurance_end_date || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Status</label>
                                    <p className="text-sm font-medium">
                                        <span className={`px-2 py-0.5 rounded-full text-xs ${getInsuranceStatusDisplay(viewVehicle.insurance_status).class}`}>
                                            {getInsuranceStatusDisplay(viewVehicle.insurance_status).text}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* PUC Details */}
                        <div className={sectionCardClass}>
                            <h3 className={sectionTitleClass}>PUC Details</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Certificate Number</label><p className="text-sm font-medium dark:text-white">{viewVehicle.puc_certificate_number || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Issue Date</label><p className="text-sm font-medium dark:text-white">{viewVehicle.puc_issue_date || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Expiry Date</label><p className="text-sm font-medium dark:text-white">{viewVehicle.puc_expiry_date || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Status</label>
                                    <p className="text-sm font-medium">
                                        <span className={`px-2 py-0.5 rounded-full text-xs ${getPucStatusDisplay(viewVehicle.puc_status).class}`}>
                                            {getPucStatusDisplay(viewVehicle.puc_status).text}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Challan Details */}
                        <div className={sectionCardClass}>
                            <h3 className={sectionTitleClass}>Challan Details</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Challan Number</label><p className="text-sm font-medium dark:text-white">{viewVehicle.challan_number || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Challan Date</label><p className="text-sm font-medium dark:text-white">{viewVehicle.challan_date || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Violation Type</label><p className="text-sm font-medium dark:text-white">{viewVehicle.violation_type || '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Fine Amount</label><p className="text-sm font-medium dark:text-white">{viewVehicle.fine_amount ? '₹ ' + parseFloat(viewVehicle.fine_amount).toLocaleString() : '-'}</p></div>
                                <div><label className="text-xs text-gray-500 dark:text-gray-400">Payment Status</label>
                                    <p className="text-sm font-medium">
                                        {viewVehicle.payment_status ? (
                                            <span className={`px-2 py-0.5 rounded-full text-xs ${viewVehicle.payment_status === 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'}`}>
                                                {viewVehicle.payment_status.charAt(0).toUpperCase() + viewVehicle.payment_status.slice(1)}
                                            </span>
                                        ) : '-'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </Modal>
    );
}