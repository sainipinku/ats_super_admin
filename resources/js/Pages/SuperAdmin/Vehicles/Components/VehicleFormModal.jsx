import { useState, useEffect, useRef } from "react";
import Modal from "@/Components/Modal";
import { FiCamera, FiTrash2 } from "react-icons/fi";

const vehicleTypes = ["Motorcycle", "Car", "SUV", "Pickup", "Truck", "JCB", "Tractor", "Other"];
const fuelTypes = ["Petrol", "Diesel", "CNG", "Electric", "Hybrid"];
const insuranceTypes = ["Third Party", "Comprehensive"];

export default function VehicleFormModal({
    isOpen,
    onClose,
    currentVehicle,
    formData,
    errors,
    isSubmitting,
    inputClass,
    selectClass,
    sectionCardClass,
    sectionTitleClass,
    handleChange,
    handleFileChange,
    handleRemoveImage,
    handleSubmit,
    fileInputRef,
    vehicleImagePreview,
    statusOptions,
}) {
    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="6xl" topCloseButton={true} handleTopClose={onClose}>
            <div className="p-2 md:p-4 dark:bg-[#080626]">
                <h2 className="text-xl font-bold mb-6 dark:text-white">
                    {currentVehicle ? "Edit Vehicle" : "Add Vehicle"}
                </h2>

                <form onSubmit={handleSubmit} encType="multipart/form-data">
                    {/* Basic Information */}
                    <div className={sectionCardClass}>
                        <h3 className={sectionTitleClass}>
                            <span className="flex items-center gap-2">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Basic Information
                            </span>
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Vehicle ID</label>
                                <input type="text" value={currentVehicle ? currentVehicle.vehicle_id : 'Auto-generated'} className={inputClass('vehicle_id')} disabled />
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Vehicle Type <em className="text-red-500">*</em></label>
                                <select name="vehicle_type" value={formData.vehicle_type} onChange={handleChange} className={selectClass('vehicle_type')}>
                                    <option value="">Select Vehicle Type</option>
                                    {vehicleTypes.map((type) => (
                                        <option key={type} value={type}>{type}</option>
                                    ))}
                                </select>
                                {errors.vehicle_type && <p className="text-red-500 text-xs mt-1">{errors.vehicle_type}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Vehicle Number <em className="text-red-500">*</em></label>
                                <input type="text" name="vehicle_number" value={formData.vehicle_number} onChange={handleChange} className={inputClass('vehicle_number')} placeholder="e.g. MH-01-AB-1234" />
                                {errors.vehicle_number && <p className="text-red-500 text-xs mt-1">{errors.vehicle_number}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Vehicle Name / Model</label>
                                <input type="text" name="vehicle_name" value={formData.vehicle_name} onChange={handleChange} className={inputClass('vehicle_name')} placeholder="e.g. Honda City" />
                                {errors.vehicle_name && <p className="text-red-500 text-xs mt-1">{errors.vehicle_name}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Brand</label>
                                <input type="text" name="brand" value={formData.brand} onChange={handleChange} className={inputClass('brand')} placeholder="e.g. Honda, Toyota" />
                                {errors.brand && <p className="text-red-500 text-xs mt-1">{errors.brand}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Fuel Type <em className="text-red-500">*</em></label>
                                <select name="fuel_type" value={formData.fuel_type} onChange={handleChange} className={selectClass('fuel_type')}>
                                    <option value="">Select Fuel Type</option>
                                    {fuelTypes.map((fuel) => (
                                        <option key={fuel} value={fuel}>{fuel}</option>
                                    ))}
                                </select>
                                {errors.fuel_type && <p className="text-red-500 text-xs mt-1">{errors.fuel_type}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Color</label>
                                <input type="text" name="color" value={formData.color} onChange={handleChange} className={inputClass('color')} placeholder="e.g. White, Red" />
                                {errors.color && <p className="text-red-500 text-xs mt-1">{errors.color}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Manufacturing Year</label>
                                <input type="text" name="manufacturing_year" value={formData.manufacturing_year} onChange={handleChange} className={inputClass('manufacturing_year')} placeholder="e.g. 2024" maxLength="4" />
                                {errors.manufacturing_year && <p className="text-red-500 text-xs mt-1">{errors.manufacturing_year}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Engine Number</label>
                                <input type="text" name="engine_number" value={formData.engine_number} onChange={handleChange} className={inputClass('engine_number')} />
                                {errors.engine_number && <p className="text-red-500 text-xs mt-1">{errors.engine_number}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Chassis Number</label>
                                <input type="text" name="chassis_number" value={formData.chassis_number} onChange={handleChange} className={inputClass('chassis_number')} />
                                {errors.chassis_number && <p className="text-red-500 text-xs mt-1">{errors.chassis_number}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Purchase Date</label>
                                <input type="date" name="purchase_date" value={formData.purchase_date} onChange={handleChange} className={inputClass('purchase_date')} />
                                {errors.purchase_date && <p className="text-red-500 text-xs mt-1">{errors.purchase_date}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Purchase Amount (₹)</label>
                                <input type="number" name="purchase_amount" value={formData.purchase_amount} onChange={handleChange} className={inputClass('purchase_amount')} placeholder="0.00" min="0" step="0.01" />
                                {errors.purchase_amount && <p className="text-red-500 text-xs mt-1">{errors.purchase_amount}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Current KM Reading</label>
                                <input type="text" name="current_km_reading" value={formData.current_km_reading} onChange={handleChange} className={inputClass('current_km_reading')} placeholder="e.g. 15000 km" />
                                {errors.current_km_reading && <p className="text-red-500 text-xs mt-1">{errors.current_km_reading}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Status <em className="text-red-500">*</em></label>
                                <select name="status" value={formData.status} onChange={handleChange} className={selectClass('status')}>
                                    {statusOptions.map((opt) => (
                                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                                    ))}
                                </select>
                                {errors.status && <p className="text-red-500 text-xs mt-1">{errors.status}</p>}
                            </div>
                            <div className="md:col-span-2">
                                <label className="block text-gray-700 dark:text-gray-300 mb-2 text-sm">Vehicle Image</label>
                                <div className="flex items-center gap-4">
                                    <div className="relative">
                                        <div className="w-24 h-24 rounded-lg overflow-hidden border-2 border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                            {vehicleImagePreview ? (
                                                <img src={vehicleImagePreview} alt="Preview" className="w-full h-full object-cover" />
                                            ) : (
                                                <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            )}
                                        </div>
                                        <button type="button" onClick={() => fileInputRef.current?.click()}
                                            className="absolute bottom-0 right-0 bg-blue-600 text-white p-1.5 rounded-full hover:bg-blue-700 transition">
                                            <FiCamera size={14} />
                                        </button>
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        <input type="file" ref={fileInputRef} onChange={handleFileChange} accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" className="hidden" />
                                        <button type="button" onClick={() => fileInputRef.current?.click()}
                                            className="px-3 py-1.5 text-xs bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                                            Choose Image
                                        </button>
                                        {vehicleImagePreview && (
                                            <button type="button" onClick={handleRemoveImage}
                                                className="px-3 py-1.5 text-xs bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-md hover:bg-red-200 dark:hover:bg-red-900/50 transition flex items-center gap-1">
                                                <FiTrash2 size={12} /> Remove
                                            </button>
                                        )}
                                    </div>
                                </div>
                                {errors.vehicle_image && <p className="text-red-500 text-xs mt-1">{errors.vehicle_image}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Insurance Details */}
                    <div className={sectionCardClass}>
                        <h3 className={sectionTitleClass}>
                            <span className="flex items-center gap-2">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                Insurance Details
                            </span>
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Insurance Provider</label>
                                <input type="text" name="insurance_provider" value={formData.insurance_provider} onChange={handleChange} className={inputClass('insurance_provider')} placeholder="e.g. ICICI Lombard" />
                                {errors.insurance_provider && <p className="text-red-500 text-xs mt-1">{errors.insurance_provider}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Policy Number</label>
                                <input type="text" name="policy_number" value={formData.policy_number} onChange={handleChange} className={inputClass('policy_number')} />
                                {errors.policy_number && <p className="text-red-500 text-xs mt-1">{errors.policy_number}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Insurance Type</label>
                                <select name="insurance_type" value={formData.insurance_type} onChange={handleChange} className={selectClass('insurance_type')}>
                                    <option value="">Select Type</option>
                                    {insuranceTypes.map((type) => (
                                        <option key={type} value={type}>{type}</option>
                                    ))}
                                </select>
                                {errors.insurance_type && <p className="text-red-500 text-xs mt-1">{errors.insurance_type}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Insurance Start Date</label>
                                <input type="date" name="insurance_start_date" value={formData.insurance_start_date} onChange={handleChange} className={inputClass('insurance_start_date')} />
                                {errors.insurance_start_date && <p className="text-red-500 text-xs mt-1">{errors.insurance_start_date}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Insurance End Date</label>
                                <input type="date" name="insurance_end_date" value={formData.insurance_end_date} onChange={handleChange} className={inputClass('insurance_end_date')} />
                                {errors.insurance_end_date && <p className="text-red-500 text-xs mt-1">{errors.insurance_end_date}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Insurance Status (Auto-calculated)</label>
                                <input type="text" value={
                                    formData.insurance_end_date
                                        ? (new Date(formData.insurance_end_date) >= new Date(new Date().toDateString()) ? 'Active' : 'Expired')
                                        : 'N/A'
                                } className={inputClass('insurance_status')} disabled />
                            </div>
                        </div>
                    </div>

                    {/* PUC Details */}
                    <div className={sectionCardClass}>
                        <h3 className={sectionTitleClass}>
                            <span className="flex items-center gap-2">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                PUC Details
                            </span>
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">PUC Certificate Number</label>
                                <input type="text" name="puc_certificate_number" value={formData.puc_certificate_number} onChange={handleChange} className={inputClass('puc_certificate_number')} />
                                {errors.puc_certificate_number && <p className="text-red-500 text-xs mt-1">{errors.puc_certificate_number}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Issue Date</label>
                                <input type="date" name="puc_issue_date" value={formData.puc_issue_date} onChange={handleChange} className={inputClass('puc_issue_date')} />
                                {errors.puc_issue_date && <p className="text-red-500 text-xs mt-1">{errors.puc_issue_date}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Expiry Date</label>
                                <input type="date" name="puc_expiry_date" value={formData.puc_expiry_date} onChange={handleChange} className={inputClass('puc_expiry_date')} />
                                {errors.puc_expiry_date && <p className="text-red-500 text-xs mt-1">{errors.puc_expiry_date}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">PUC Status (Auto-calculated)</label>
                                <input type="text" value={
                                    formData.puc_expiry_date
                                        ? (new Date(formData.puc_expiry_date) >= new Date(new Date().toDateString()) ? 'Valid' : 'Expired')
                                        : 'N/A'
                                } className={inputClass('puc_status')} disabled />
                            </div>
                        </div>
                    </div>

                    {/* Challan Details */}
                    <div className={sectionCardClass}>
                        <h3 className={sectionTitleClass}>
                            <span className="flex items-center gap-2">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Challan Details
                            </span>
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Challan Number</label>
                                <input type="text" name="challan_number" value={formData.challan_number} onChange={handleChange} className={inputClass('challan_number')} />
                                {errors.challan_number && <p className="text-red-500 text-xs mt-1">{errors.challan_number}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Challan Date</label>
                                <input type="date" name="challan_date" value={formData.challan_date} onChange={handleChange} className={inputClass('challan_date')} />
                                {errors.challan_date && <p className="text-red-500 text-xs mt-1">{errors.challan_date}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Violation Type</label>
                                <input type="text" name="violation_type" value={formData.violation_type} onChange={handleChange} className={inputClass('violation_type')} placeholder="e.g. Over Speeding" />
                                {errors.violation_type && <p className="text-red-500 text-xs mt-1">{errors.violation_type}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Fine Amount (₹)</label>
                                <input type="number" name="fine_amount" value={formData.fine_amount} onChange={handleChange} className={inputClass('fine_amount')} placeholder="0.00" min="0" step="0.01" />
                                {errors.fine_amount && <p className="text-red-500 text-xs mt-1">{errors.fine_amount}</p>}
                            </div>
                            <div>
                                <label className="block text-gray-700 dark:text-gray-300 mb-1 text-sm">Payment Status</label>
                                <select name="payment_status" value={formData.payment_status} onChange={handleChange} className={selectClass('payment_status')}>
                                    <option value="">Select Payment Status</option>
                                    <option value={1}>Paid</option>
                                    <option value={0}>Unpaid</option>
                                </select>
                                {errors.payment_status && <p className="text-red-500 text-xs mt-1">{errors.payment_status}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Submit Buttons */}
                    <div className="flex justify-end space-x-3 mt-6">
                        <button type="button" onClick={onClose} className="px-4 py-2 canclebtn rounded-[7px]">Cancel</button>
                        <button type="submit"
                            className={`flex items-center gap-[5px] px-[20px] py-[12px] text-[15px] text-white rounded-[10px] bluebtbg ${isSubmitting ? "opacity-75 cursor-not-allowed" : ""}`}
                            disabled={isSubmitting}>
                            {isSubmitting ? (
                                <span className="flex items-center justify-center">
                                    <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                    {currentVehicle ? "Updating..." : "Adding..."}
                                </span>
                            ) : currentVehicle ? "Update Vehicle" : "Add Vehicle"}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}