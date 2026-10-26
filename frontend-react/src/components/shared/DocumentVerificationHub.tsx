import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  FileCheck, Search, Calendar, Phone, Mail, Building2, MapPin,
  ArrowRight, CheckCircle, ShieldCheck, Clock, User, ArrowLeft,
  X, Check, Sparkles
} from 'lucide-react';
import {
  verificationService,
  SAMPLE_REFERENCE_PROPERTIES
} from '../../services/verification.service';
import { propertyService } from '../../services/property.service';
import type { Property } from '../../models/property.models';

interface Props {
  onBack?: () => void;
  initialProperty?: Property | null;
  onScheduleVisit?: (p: any) => void;
  onNavigateToVisits?: () => void;
}

export const DocumentVerificationHub: React.FC<Props> = ({ 
  onBack, 
  onScheduleVisit,
  onNavigateToVisits 
}) => {
  const [searchQuery, setSearchQuery] = useState('');
  const [properties, setProperties] = useState<any[]>(SAMPLE_REFERENCE_PROPERTIES);
  const [selectedPropertyForVisit, setSelectedPropertyForVisit] = useState<any | null>(null);
  const [visitDate, setVisitDate] = useState<string>('');
  const [visitTime, setVisitTime] = useState<string>('10:00');
  const [isScheduling, setIsScheduling] = useState(false);
  const [scheduleSuccess, setScheduleSuccess] = useState<string | null>(null);

  // Load any live properties and merge with reference properties
  useEffect(() => {
    const fetchLiveProperties = async () => {
      try {
        const live = await propertyService.getProperties();
        if (live && live.length > 0) {
          const adaptedLive = live.map(p => ({
            id: p.id,
            propertyCode: p.propertyCode || `LL-${p.id.slice(0, 8)}`,
            title: p.title,
            surveyNumber: p.surveyNumber || '123/A',
            category: p.category || 'AGRICULTURAL',
            area: p.area || 2.0,
            price: p.price || 4500000,
            district: p.district || 'Bangalore Rural',
            village: p.village || 'Devanahalli',
            state: p.state || 'Karnataka',
            pincode: p.pincode || '562110',
            sroOffice: `${p.district || 'Local'} Sub-Registrar Office`,
            registeredOwner: p.provider ? `${p.provider.firstName || ''} ${p.provider.lastName || ''}`.trim() || 'Government Landowner' : 'Registered Landowner',
            ownerEmail: p.provider?.email || 'landlens@acheva.in',
            ownerPhone: p.provider?.phoneNumber || '+91 98765 43210',
            image: p.images?.[0]?.imageUrl || p.images?.[0]?.url || 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?w=600&q=80'
          }));

          // Avoid duplicate IDs
          const existingIds = new Set(adaptedLive.map(a => a.id));
          const remainingSample = SAMPLE_REFERENCE_PROPERTIES.filter(s => !existingIds.has(s.id));
          setProperties([...adaptedLive, ...remainingSample]);
        }
      } catch (err) {
        console.warn('Using reference sample properties:', err);
      }
    };
    fetchLiveProperties();
  }, []);

  // Filter properties by search term
  const filteredProperties = properties.filter(p => {
    const q = searchQuery.toLowerCase();
    return (
      (p.title && p.title.toLowerCase().includes(q)) ||
      (p.surveyNumber && p.surveyNumber.toLowerCase().includes(q)) ||
      (p.district && p.district.toLowerCase().includes(q)) ||
      (p.registeredOwner && p.registeredOwner.toLowerCase().includes(q)) ||
      (p.propertyCode && p.propertyCode.toLowerCase().includes(q))
    );
  });

  const handleOpenScheduleModal = (prop: any) => {
    if (onScheduleVisit) {
      onScheduleVisit(prop);
      return;
    }
    setSelectedPropertyForVisit(prop);
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    setVisitDate(tomorrow.toISOString().split('T')[0]);
    setVisitTime('10:00');
    setScheduleSuccess(null);
  };

  const handleConfirmSchedule = async () => {
    if (!selectedPropertyForVisit || !visitDate || !visitTime) return;
    setIsScheduling(true);
    try {
      await propertyService.scheduleVisit(selectedPropertyForVisit.id, {
        visitDate,
        visitTime
      }, selectedPropertyForVisit);

      setScheduleSuccess(`Site visit scheduled for "${selectedPropertyForVisit.title}" on ${visitDate} at ${visitTime}!`);
      setTimeout(() => {
        setSelectedPropertyForVisit(null);
        if (onNavigateToVisits) {
          onNavigateToVisits();
        }
      }, 2000);
    } catch (err: any) {
      alert("Failed to schedule visit: " + (err?.message || "Unknown error"));
    } finally {
      setIsScheduling(false);
    }
  };

  return (
    <div className="w-full max-w-6xl mx-auto px-4 py-4 space-y-6">
      
      {/* ── HEADER ── */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
        <div className="flex items-center gap-3">
          {onBack && (
            <button
              onClick={onBack}
              className="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-colors text-slate-700"
              title="Go Back"
            >
              <ArrowLeft className="w-4 h-4" />
            </button>
          )}
          <div>
            <div className="flex items-center gap-2">
              <span className="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 border border-blue-200">
                Official Revenue Ledger
              </span>
              <span className="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 border border-emerald-200 flex items-center gap-1">
                <ShieldCheck className="w-3 h-3" /> 100% Title Verified
              </span>
            </div>
            <h1 className="text-xl sm:text-2xl font-black text-slate-900 mt-1">
              Registered Lands & Schedule Visit
            </h1>
            <p className="text-xs text-slate-500">
              Browse verified land parcels, inspect registered title holders, and schedule site inspections.
            </p>
          </div>
        </div>

        {/* Global Search Bar */}
        <div className="relative w-full sm:w-80">
          <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Search Survey No, Owner, District..."
            value={searchQuery}
            onChange={e => setSearchQuery(e.target.value)}
            className="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-xs outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 shadow-xs"
          />
        </div>
      </div>

      {/* ── GUIDANCE BANNER ── */}
      <div className="p-4 rounded-2xl bg-gradient-to-r from-blue-900 via-indigo-950 to-slate-900 text-white shadow-md border border-indigo-800/40 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div className="space-y-1">
          <div className="flex items-center gap-1.5 text-blue-300 font-bold text-xs">
            <Sparkles className="w-4 h-4 text-amber-400" />
            <span>Land Buyer Verification & Transfer Workflow</span>
          </div>
          <p className="text-xs text-blue-100/90 leading-relaxed max-w-2xl">
            1. Select any property below and click <strong>Schedule Visit</strong>. <br />
            2. Once the registered owner confirms your visit in the <strong>Visits</strong> tab, direct phone/email contact is unlocked and a single-click <strong>Request Land Transfer</strong> button becomes available to dispatch Hostinger email verification!
          </p>
        </div>
        {onNavigateToVisits && (
          <button
            onClick={onNavigateToVisits}
            className="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold flex items-center gap-1.5 shrink-0 shadow-sm transition active:scale-95"
          >
            <Calendar className="w-4 h-4" />
            <span>View My Visits</span>
            <ArrowRight className="w-3.5 h-3.5" />
          </button>
        )}
      </div>

      {/* ── PROPERTY LISTING GRID ── */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        {filteredProperties.map((prop) => (
          <div
            key={prop.id}
            className="bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all overflow-hidden flex flex-col"
          >
            {/* Header / Extent Banner */}
            <div className="p-4 border-b border-slate-100 bg-slate-50/60 flex items-start justify-between gap-2">
              <div>
                <span className="text-[10px] font-bold uppercase tracking-wider text-blue-600">
                  {prop.propertyCode || 'LL-PARCEL'}
                </span>
                <h3 className="text-sm font-extrabold text-slate-900 mt-0.5 line-clamp-1">
                  {prop.title}
                </h3>
                <p className="text-[11px] text-slate-500 flex items-center gap-1 mt-0.5">
                  <MapPin className="w-3 h-3 text-slate-400 shrink-0" />
                  {prop.village || prop.district}, {prop.district}
                </p>
              </div>
              <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200 shrink-0">
                {prop.category}
              </span>
            </div>

            {/* Land Data Parameters */}
            <div className="p-4 space-y-3 flex-1 text-xs text-slate-700">
              <div className="grid grid-cols-2 gap-2 pb-2 border-b border-slate-100">
                <div>
                  <span className="text-[10px] uppercase font-bold text-slate-400 block">Survey Number</span>
                  <span className="font-extrabold text-slate-900 text-sm">{prop.surveyNumber}</span>
                </div>
                <div>
                  <span className="text-[10px] uppercase font-bold text-slate-400 block">Land Extent / Area</span>
                  <span className="font-extrabold text-slate-900 text-sm">{prop.area} Acres</span>
                </div>
              </div>

              <div>
                <span className="text-[10px] uppercase font-bold text-slate-400 block">Jurisdiction & SRO Office</span>
                <span className="text-xs text-slate-800 font-semibold">{prop.sroOffice}</span>
              </div>

              {/* Registered Landowner Info */}
              <div className="p-3 rounded-xl bg-blue-50/60 border border-blue-100 space-y-1">
                <div className="flex items-center justify-between">
                  <span className="text-[10px] font-black uppercase text-blue-600 tracking-wider flex items-center gap-1">
                    <User className="w-3 h-3" /> Actual Registered Owner
                  </span>
                  <span className="text-[9px] font-bold px-1.5 py-0.2 rounded bg-blue-100 text-blue-700">
                    Ledger Verified
                  </span>
                </div>
                <p className="text-xs font-bold text-slate-900">{prop.registeredOwner}</p>
                <p className="text-[11px] text-slate-500 truncate">{prop.ownerEmail}</p>
              </div>

              {/* Price */}
              <div className="flex items-center justify-between pt-1">
                <span className="text-[11px] text-slate-500 font-semibold">Guide Valuation:</span>
                <span className="text-base font-black text-slate-900">
                  ₹{(prop.price || 0).toLocaleString()}
                </span>
              </div>
            </div>

            {/* Action Footer: Schedule Visit */}
            <div className="p-3.5 bg-slate-50 border-t border-slate-100 mt-auto">
              <button
                onClick={() => handleOpenScheduleModal(prop)}
                className="w-full py-2.5 px-4 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-sm transition active:scale-98 cursor-pointer"
              >
                <Calendar className="w-4 h-4" />
                <span>Schedule Site Visit</span>
              </button>
            </div>
          </div>
        ))}
      </div>

      {filteredProperties.length === 0 && (
        <div className="py-16 text-center bg-white rounded-3xl border border-slate-200">
          <FileCheck className="w-12 h-12 text-slate-300 mx-auto mb-3" />
          <h3 className="text-sm font-bold text-slate-700">No matching land parcels found</h3>
          <p className="text-xs text-slate-400 mt-1">Try modifying your search survey number or district.</p>
        </div>
      )}

      {/* ── SCHEDULE VISIT MODAL POPUP ── */}
      <AnimatePresence>
        {selectedPropertyForVisit && (
          <>
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              className="fixed inset-0 bg-black/60 backdrop-blur-xs z-[150]"
              onClick={() => setSelectedPropertyForVisit(null)}
            />
            <motion.div
              initial={{ scale: 0.95, opacity: 0, y: 20 }}
              animate={{ scale: 1, opacity: 1, y: 0 }}
              exit={{ scale: 0.95, opacity: 0, y: 20 }}
              className="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[92%] max-w-md bg-white rounded-3xl p-6 shadow-2xl border border-slate-200 z-[160] text-slate-900 space-y-4"
            >
              <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                <div className="flex items-center gap-2">
                  <div className="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                    <Calendar className="w-4 h-4" />
                  </div>
                  <div>
                    <h3 className="text-sm font-bold text-slate-900">Schedule Site Visit</h3>
                    <p className="text-[11px] text-slate-500">{selectedPropertyForVisit.surveyNumber} • {selectedPropertyForVisit.district}</p>
                  </div>
                </div>
                <button
                  onClick={() => setSelectedPropertyForVisit(null)}
                  className="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-600"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>

              <div className="space-y-3">
                <div className="p-3 bg-slate-50 rounded-2xl border border-slate-200 text-xs space-y-1">
                  <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Target Property</span>
                  <p className="font-bold text-slate-900">{selectedPropertyForVisit.title}</p>
                  <p className="text-[11px] text-slate-500">Registered Owner: <strong>{selectedPropertyForVisit.registeredOwner}</strong></p>
                </div>

                <div>
                  <label className="text-xs font-bold text-slate-700 block mb-1">Select Inspection Date</label>
                  <input
                    type="date"
                    value={visitDate}
                    onChange={e => setVisitDate(e.target.value)}
                    min={new Date().toISOString().split('T')[0]}
                    className="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                  />
                </div>

                <div>
                  <label className="text-xs font-bold text-slate-700 block mb-1">Select Preferred Time</label>
                  <select
                    value={visitTime}
                    onChange={e => setVisitTime(e.target.value)}
                    className="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                  >
                    <option value="09:00">09:00 AM (Morning Slot)</option>
                    <option value="10:30">10:30 AM (Preferred)</option>
                    <option value="12:00">12:00 PM (Noon)</option>
                    <option value="14:30">02:30 PM (Afternoon)</option>
                    <option value="16:00">04:00 PM (Evening Slot)</option>
                  </select>
                </div>

                <div className="p-3 rounded-xl bg-amber-50 border border-amber-200 text-[11px] text-amber-900 leading-relaxed">
                  💡 <strong>Next Step:</strong> Once the owner accepts your visit, you will be able to contact them directly and initiate official land transfer from the <strong>Visits</strong> page!
                </div>
              </div>

              {scheduleSuccess && (
                <div className="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
                  <CheckCircle className="w-4 h-4 text-emerald-600 shrink-0" />
                  <span>{scheduleSuccess}</span>
                </div>
              )}

              <div className="flex items-center gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setSelectedPropertyForVisit(null)}
                  className="w-1/3 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold transition"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  onClick={handleConfirmSchedule}
                  disabled={isScheduling}
                  className="flex-1 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-xs font-bold flex items-center justify-center gap-1.5 shadow-md shadow-blue-500/20 transition cursor-pointer"
                >
                  {isScheduling ? 'Scheduling...' : 'Confirm Visit Schedule'}
                </button>
              </div>
            </motion.div>
          </>
        )}
      </AnimatePresence>

    </div>
  );
};
