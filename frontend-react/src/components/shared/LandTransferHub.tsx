import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
  Send, ShieldCheck, CheckCircle2, XCircle, Clock, AlertTriangle, 
  ArrowRight, Mail, User, FileText, MapPin, RefreshCw, Copy, Check, ExternalLink, Sparkles
} from 'lucide-react';
import { propertyService } from '../../services/property.service';
import { authService } from '../../services/auth.service';
import type { Property, LandTransfer } from '../../models/property.models';

interface LandTransferHubProps {
  onBack?: () => void;
  preselectedPropertyId?: string;
}

export const LandTransferHub: React.FC<LandTransferHubProps> = ({ onBack, preselectedPropertyId }) => {
  const currentUser = authService.currentUser();
  const [properties, setProperties] = useState<Property[]>([]);
  const [transfers, setTransfers] = useState<LandTransfer[]>([]);
  const [loading, setLoading] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [copiedToken, setCopiedToken] = useState<string | null>(null);

  // Form State
  const [selectedPropertyId, setSelectedPropertyId] = useState<string>(preselectedPropertyId || '');
  const [buyerName, setBuyerName] = useState('');
  const [buyerEmail, setBuyerEmail] = useState('');
  const [ownerEmail, setOwnerEmail] = useState('');
  const [transferAmount, setTransferAmount] = useState<number | ''>('');
  const [remarks, setRemarks] = useState('Official Title Conveyance via LandLens Government Registry');

  // Result / Success Modal
  const [lastTransferResult, setLastTransferResult] = useState<any | null>(null);
  const [statusMessage, setStatusMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    setLoading(true);
    try {
      const [props, trfs] = await Promise.all([
        propertyService.getProperties(),
        propertyService.getLandTransfers(currentUser?.id)
      ]);
      setProperties(props);
      setTransfers(trfs);

      if (preselectedPropertyId) {
        setSelectedPropertyId(preselectedPropertyId);
        const p = props.find(x => x.id === preselectedPropertyId);
        if (p) {
          setTransferAmount(p.price);
        }
      } else if (props.length > 0 && !selectedPropertyId) {
        setSelectedPropertyId(props[0].id);
        setTransferAmount(props[0].price);
      }
    } catch (err) {
      console.error('Failed to load transfers', err);
    } finally {
      setLoading(false);
    }
  };

  const handlePropertyChange = (propId: string) => {
    setSelectedPropertyId(propId);
    const p = properties.find(x => x.id === propId);
    if (p) {
      setTransferAmount(p.price);
    }
  };

  const selectedProperty = properties.find(p => p.id === selectedPropertyId);

  const handleSubmitTransfer = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedPropertyId) {
      setStatusMessage({ type: 'error', text: 'Please select a land parcel to transfer.' });
      return;
    }
    if (!buyerEmail) {
      setStatusMessage({ type: 'error', text: 'Please provide the buyer email address.' });
      return;
    }

    setIsSubmitting(true);
    setStatusMessage(null);

    try {
      const res = await propertyService.requestLandTransfer({
        propertyId: selectedPropertyId,
        buyerId: currentUser?.id || `usr-${Date.now()}`,
        buyerName: buyerName || 'Citizen Buyer',
        buyerEmail: buyerEmail,
        ownerEmail: ownerEmail || 'landlens@acheva.in',
        transferAmount: typeof transferAmount === 'number' ? transferAmount : 0,
        remarks: remarks
      });

      setLastTransferResult(res);
      setStatusMessage({
        type: 'success',
        text: `Confirmation email successfully sent via Hostinger SMTP to ${res.ownerEmail || 'registered owner'} with Accept and Reject verification links!`
      });

      // Reload transfers list
      const updatedTrfs = await propertyService.getLandTransfers(currentUser?.id);
      setTransfers(updatedTrfs);
    } catch (err: any) {
      console.error('Transfer request error', err);
      setStatusMessage({
        type: 'error',
        text: err.response?.data?.message || 'Failed to dispatch land transfer confirmation email.'
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleSimulateDecision = async (token: string, action: 'APPROVE' | 'REJECT') => {
    setLoading(true);
    try {
      await propertyService.respondToLandTransfer(token, action);
      setStatusMessage({
        type: action === 'APPROVE' ? 'success' : 'error',
        text: action === 'APPROVE' 
          ? 'Transfer APPROVED! Land ownership title updated to buyer in government database.'
          : 'Transfer REJECTED! Land conveyance blocked to protect registered owner.'
      });
      // Refresh both properties and transfers
      const [props, trfs] = await Promise.all([
        propertyService.getProperties(),
        propertyService.getLandTransfers(currentUser?.id)
      ]);
      setProperties(props);
      setTransfers(trfs);
    } catch (err: any) {
      setStatusMessage({
        type: 'error',
        text: err.response?.data?.message || 'Failed to update transfer status.'
      });
    } finally {
      setLoading(false);
    }
  };

  const copyToClipboard = (text: string, id: string) => {
    navigator.clipboard.writeText(text);
    setCopiedToken(id);
    setTimeout(() => setCopiedToken(null), 2500);
  };

  return (
    <div className="max-w-6xl mx-auto space-y-8 pb-16">
      {/* ── HEADER BANNER ── */}
      <div className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 border border-blue-900/40 p-6 sm:p-8 shadow-xl text-white">
        <div className="absolute top-0 right-0 w-80 h-80 bg-blue-500/10 rounded-full blur-3xl pointer-events-none" />
        <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div className="space-y-2">
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 text-xs font-bold uppercase tracking-wider">
              <ShieldCheck className="w-4 h-4" />
              Verified Land Title Conveyance Engine
            </div>
            <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight">
              Land Selling & Ownership Transfer
            </h1>
            <p className="text-slate-300 text-sm max-w-2xl leading-relaxed">
              When transferring land ownership, LandLens sends an automated cryptographic confirmation email with clickable <strong>Accept</strong> and <strong>Reject</strong> buttons directly to the registered owner's inbox via Hostinger SMTP (<code className="text-emerald-400">landlens@acheva.in</code>). Once approved, the land title is officially transferred to the buyer. If rejected, no transfer is done.
            </p>
          </div>

          <div className="flex items-center gap-3 shrink-0">
            <button 
              onClick={loadData} 
              disabled={loading}
              className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/10 text-xs font-semibold backdrop-blur-md transition-all"
            >
              <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} />
              Refresh Status
            </button>
            {onBack && (
              <button 
                onClick={onBack}
                className="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-xs font-semibold transition-all shadow-md"
              >
                Back to Dashboard
              </button>
            )}
          </div>
        </div>
      </div>

      {/* Status Alerts */}
      <AnimatePresence>
        {statusMessage && (
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            className={`p-4 rounded-2xl border text-sm flex items-start gap-3 shadow-md ${
              statusMessage.type === 'success'
                ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300'
                : 'bg-rose-500/10 border-rose-500/30 text-rose-300'
            }`}
          >
            {statusMessage.type === 'success' ? (
              <CheckCircle2 className="w-5 h-5 shrink-0 text-emerald-400 mt-0.5" />
            ) : (
              <AlertTriangle className="w-5 h-5 shrink-0 text-rose-400 mt-0.5" />
            )}
            <div className="flex-1 font-medium leading-relaxed">{statusMessage.text}</div>
            <button onClick={() => setStatusMessage(null)} className="text-slate-400 hover:text-white">✕</button>
          </motion.div>
        )}
      </AnimatePresence>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        {/* ── TRANSFER INITIATION FORM ── */}
        <div className="lg:col-span-6 bg-white rounded-3xl border border-gray-200/80 p-6 sm:p-7 shadow-xs">
          <div className="flex items-center justify-between pb-4 mb-6 border-b border-gray-100">
            <div>
              <h2 className="text-lg font-bold text-gray-900">Initiate Land Transfer</h2>
              <p className="text-xs text-gray-500">Send owner authorization email via Hostinger</p>
            </div>
            <span className="p-2.5 rounded-2xl bg-blue-50 text-blue-600">
              <Mail className="w-5 h-5" />
            </span>
          </div>

          <form onSubmit={handleSubmitTransfer} className="space-y-4">
            {/* Property Selector */}
            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-1.5">
                Select Land Parcel to Sell / Transfer *
              </label>
              <select
                value={selectedPropertyId}
                onChange={(e) => handlePropertyChange(e.target.value)}
                required
                className="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm font-medium text-gray-900 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:bg-white"
              >
                {properties.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.title} • Sy No: {p.surveyNumber} ({p.area} Acres - ₹{Number(p.price).toLocaleString('en-IN')})
                  </option>
                ))}
              </select>
            </div>

            {/* Selected Property Quick Card */}
            {selectedProperty && (
              <div className="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-xs text-slate-600 space-y-1.5">
                <div className="flex justify-between items-center text-slate-800 font-semibold">
                  <span className="truncate">{selectedProperty.title}</span>
                  <span className="text-blue-600 font-mono bg-blue-50 px-2 py-0.5 rounded-md">
                    Sy: {selectedProperty.surveyNumber}
                  </span>
                </div>
                <div className="flex items-center gap-3 text-[11px] text-slate-500">
                  <span className="flex items-center gap-1">
                    <MapPin className="w-3 h-3" />
                    {selectedProperty.village}, {selectedProperty.district}
                  </span>
                  <span>•</span>
                  <span>{selectedProperty.area} Acres</span>
                  <span>•</span>
                  <span className="text-emerald-600 font-bold">₹{Number(selectedProperty.price).toLocaleString('en-IN')}</span>
                </div>
              </div>
            )}

            {/* Buyer Details */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">
                  Transferee / Buyer Full Name *
                </label>
                <div className="relative">
                  <User className="w-4 h-4 absolute left-3 top-3 text-gray-400" />
                  <input
                    type="text"
                    required
                    placeholder="e.g. Ramesh Reddy"
                    value={buyerName}
                    onChange={(e) => setBuyerName(e.target.value)}
                    className="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 pl-9 pr-3 text-sm text-gray-900 focus:ring-2 focus:ring-blue-500 focus:bg-white"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">
                  Buyer Email Address *
                </label>
                <div className="relative">
                  <Mail className="w-4 h-4 absolute left-3 top-3 text-gray-400" />
                  <input
                    type="email"
                    required
                    placeholder="buyer@example.com"
                    value={buyerEmail}
                    onChange={(e) => setBuyerEmail(e.target.value)}
                    className="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 pl-9 pr-3 text-sm text-gray-900 focus:ring-2 focus:ring-blue-500 focus:bg-white"
                  />
                </div>
              </div>
            </div>

            {/* Consideration Amount & Owner Email */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">
                  Agreed Consideration (₹)
                </label>
                <input
                  type="number"
                  placeholder="₹ 15,00,000"
                  value={transferAmount}
                  onChange={(e) => setTransferAmount(e.target.value === '' ? '' : Number(e.target.value))}
                  className="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm text-gray-900 focus:ring-2 focus:ring-blue-500 focus:bg-white"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">
                  Owner Email (To Receive Confirmation Link)
                </label>
                <input
                  type="email"
                  placeholder="landlens@acheva.in"
                  value={ownerEmail}
                  onChange={(e) => setOwnerEmail(e.target.value)}
                  className="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm text-gray-900 focus:ring-2 focus:ring-blue-500 focus:bg-white"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-1">
                Conveyance Notes / Purpose
              </label>
              <textarea
                rows={2}
                value={remarks}
                onChange={(e) => setRemarks(e.target.value)}
                className="w-full bg-gray-50 border border-gray-200 rounded-xl p-2.5 text-xs text-gray-900 focus:ring-2 focus:ring-blue-500 focus:bg-white"
              />
            </div>

            <button
              type="submit"
              disabled={isSubmitting || !selectedPropertyId || !buyerEmail}
              className="w-full mt-3 py-3.5 px-4 rounded-xl bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700 hover:from-blue-500 hover:to-indigo-500 text-white font-bold text-sm shadow-lg shadow-blue-500/20 active:scale-98 transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:pointer-events-none"
            >
              {isSubmitting ? (
                <>
                  <RefreshCw className="w-4 h-4 animate-spin" />
                  Dispatching Hostinger Confirmation Mail...
                </>
              ) : (
                <>
                  <Send className="w-4 h-4" />
                  Send Confirmation Email with Accept/Reject Links
                </>
              )}
            </button>
          </form>

          {/* Real-time Email Preview card if just sent */}
          {lastTransferResult && (
            <motion.div
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              className="mt-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-slate-800 space-y-3"
            >
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold uppercase tracking-wider text-emerald-800 flex items-center gap-1.5">
                  <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                  Email Dispatched via Hostinger SMTP
                </span>
                <span className="text-[10px] font-mono bg-white px-2 py-0.5 rounded-full border border-emerald-200 text-emerald-700">
                  {lastTransferResult.status}
                </span>
              </div>

              <p className="text-xs text-emerald-900">
                An authorization request was dispatched to <strong>{lastTransferResult.ownerEmail}</strong>. Click below to simulate or test the email response:
              </p>

              <div className="grid grid-cols-2 gap-2 pt-1">
                <a
                  href={lastTransferResult.approveUrl}
                  target="_blank"
                  rel="noreferrer"
                  className="py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold text-center flex items-center justify-center gap-1 shadow-xs"
                >
                  <CheckCircle2 className="w-3.5 h-3.5" />
                  Simulate Accept Link
                </a>
                <a
                  href={lastTransferResult.rejectUrl}
                  target="_blank"
                  rel="noreferrer"
                  className="py-2 px-3 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold text-center flex items-center justify-center gap-1 shadow-xs"
                >
                  <XCircle className="w-3.5 h-3.5" />
                  Simulate Reject Link
                </a>
              </div>
            </motion.div>
          )}
        </div>

        {/* ── LIVE TRANSFER AUDIT REGISTRY ── */}
        <div className="lg:col-span-6 space-y-4">
          <div className="bg-white rounded-3xl border border-gray-200/80 p-6 sm:p-7 shadow-xs">
            <div className="flex items-center justify-between pb-4 mb-4 border-b border-gray-100">
              <div>
                <h2 className="text-lg font-bold text-gray-900">Transfers Registry</h2>
                <p className="text-xs text-gray-500">Live landowner verification audit logs</p>
              </div>
              <span className="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-bold">
                {transfers.length} Recorded
              </span>
            </div>

            {loading && transfers.length === 0 ? (
              <div className="py-12 flex flex-col items-center justify-center text-gray-400 gap-2">
                <RefreshCw className="w-6 h-6 animate-spin text-blue-500" />
                <span className="text-xs">Fetching transfer records...</span>
              </div>
            ) : transfers.length === 0 ? (
              <div className="py-12 text-center text-gray-400 space-y-2">
                <ShieldCheck className="w-10 h-10 mx-auto text-gray-300" />
                <p className="text-sm font-semibold text-gray-600">No Land Transfers Initiated Yet</p>
                <p className="text-xs text-gray-400 max-w-xs mx-auto">
                  Select a land parcel on the left to initiate a title transfer with landowner email confirmation.
                </p>
              </div>
            ) : (
              <div className="space-y-3.5 max-h-[580px] overflow-y-auto pr-1">
                {transfers.map((trf) => {
                  const isApproved = trf.status === 'APPROVED';
                  const isRejected = trf.status === 'REJECTED';
                  const isPending = trf.status === 'PENDING';

                  return (
                    <div
                      key={trf.id}
                      className={`p-4 rounded-2xl border transition-all ${
                        isApproved
                          ? 'bg-emerald-50/50 border-emerald-200'
                          : isRejected
                          ? 'bg-rose-50/50 border-rose-200'
                          : 'bg-white border-gray-200 shadow-xs'
                      }`}
                    >
                      <div className="flex items-start justify-between gap-2 mb-2">
                        <div>
                          <h4 className="text-sm font-bold text-gray-900">{trf.propertyTitle}</h4>
                          <span className="text-[11px] font-mono text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md">
                            Sy No: {trf.surveyNumber}
                          </span>
                        </div>
                        
                        <span className={`text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full border ${
                          isApproved
                            ? 'bg-emerald-100 border-emerald-300 text-emerald-800'
                            : isRejected
                            ? 'bg-rose-100 border-rose-300 text-rose-800'
                            : 'bg-amber-100 border-amber-300 text-amber-800 animate-pulse'
                        }`}>
                          {isApproved ? '✓ TRANSFERRED' : isRejected ? '✕ REJECTED' : '⏳ PENDING APPROVAL'}
                        </span>
                      </div>

                      <div className="grid grid-cols-2 gap-2 text-xs text-gray-600 my-2.5 bg-gray-50/80 p-2.5 rounded-xl border border-gray-100">
                        <div>
                          <span className="text-[10px] uppercase text-gray-400 block font-semibold">Owner (Approver)</span>
                          <span className="font-medium text-gray-800 truncate block">{trf.currentOwnerEmail}</span>
                        </div>
                        <div>
                          <span className="text-[10px] uppercase text-gray-400 block font-semibold">Transferee (Buyer)</span>
                          <span className="font-medium text-gray-800 truncate block">{trf.buyerName} ({trf.buyerEmail})</span>
                        </div>
                        <div>
                          <span className="text-[10px] uppercase text-gray-400 block font-semibold">Consideration</span>
                          <span className="font-bold text-emerald-600">₹{Number(trf.transferAmount).toLocaleString('en-IN')}</span>
                        </div>
                        <div>
                          <span className="text-[10px] uppercase text-gray-400 block font-semibold">Requested At</span>
                          <span className="text-gray-500 font-mono text-[11px]">{new Date(trf.requestedAt).toLocaleDateString()}</span>
                        </div>
                      </div>

                      {trf.ownerRemarks && (
                        <p className="text-[11px] text-gray-500 italic mb-2">
                          "{trf.ownerRemarks}"
                        </p>
                      )}

                      {/* Pending Action Simulation for fast testing */}
                      {isPending && (
                        <div className="pt-2 border-t border-gray-100 flex items-center justify-between gap-2">
                          <span className="text-[10px] text-gray-400">Waiting for owner confirmation email...</span>
                          <div className="flex items-center gap-1.5">
                            <button
                              onClick={() => handleSimulateDecision(trf.id, 'APPROVE')}
                              className="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-[11px] font-bold transition-all shadow-xs"
                            >
                              Approve
                            </button>
                            <button
                              onClick={() => handleSimulateDecision(trf.id, 'REJECT')}
                              className="px-2.5 py-1 rounded-lg bg-rose-600 hover:bg-rose-500 text-white text-[11px] font-bold transition-all shadow-xs"
                            >
                              Reject
                            </button>
                          </div>
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};
