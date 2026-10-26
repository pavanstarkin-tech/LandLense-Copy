import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Plus, Home, Tag, MapPin, IndianRupee, FileText, CheckCircle2,
  AlertCircle, Sparkles, Image, RefreshCw, Send, ArrowRight, Eye, ShieldCheck, Trash2
} from 'lucide-react';
import { propertyService } from '../../services/property.service';
import { authService } from '../../services/auth.service';
import { aiService } from '../../services/ai.service';
import type { Property } from '../../models/property.models';

interface LandSellerHubProps {
  onInitiateTransfer?: (propertyId: string) => void;
  onExploreLands?: () => void;
}

export const LandSellerHub: React.FC<LandSellerHubProps> = ({ onInitiateTransfer, onExploreLands }) => {
  const currentUser = authService.currentUser();
  const [activeTab, setActiveTab] = useState<'listings' | 'add'>('listings');
  const [myProperties, setMyProperties] = useState<Property[]>([]);
  const [loading, setLoading] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [aiValuationLoading, setAiValuationLoading] = useState(false);
  const [aiPriceHint, setAiPriceHint] = useState<string | null>(null);

  // Form State
  const initialForm = {
    title: '',
    category: 'AGRICULTURAL' as const,
    area: '',
    price: '',
    description: '',
    surveyNumber: '',
    address: '',
    latitude: 17.3850,
    longitude: 78.4867,
    district: 'Hyderabad',
    village: 'Shamshabad',
    state: 'Telangana',
    pincode: '500001',
    threeSixtyImageUrl: ''
  };
  const [form, setForm] = useState(initialForm);
  const [successToast, setSuccessToast] = useState<string | null>(null);
  const [errorToast, setErrorToast] = useState<string | null>(null);

  useEffect(() => {
    loadMyListings();
  }, []);

  const loadMyListings = async () => {
    setLoading(true);
    try {
      const all = await propertyService.getProperties();
      const userId = currentUser?.id || localStorage.getItem('user_id');
      const userEmail = currentUser?.email || '';

      // Filter properties owned or listed by this user, or show top listed ones
      const mine = all.filter(p => 
        (userId && p.providerId === userId) ||
        (userEmail && p.description?.includes(userEmail))
      );

      // If user hasn't created any yet, show recent ones so they can see interactive demo
      setMyProperties(mine.length > 0 ? mine : all.slice(0, 4));
    } catch (err) {
      console.error('Failed to load listings', err);
    } finally {
      setLoading(false);
    }
  };

  const handleAiValuation = async () => {
    if (!form.district || !form.area) {
      setErrorToast('Please enter District and Land Area in Acres to run AI valuation.');
      return;
    }
    setAiValuationLoading(true);
    setAiPriceHint(null);
    try {
      const prompt = `Give a realistic fair market land valuation per acre and total valuation for a ${form.area} acre ${form.category} parcel in ${form.village}, ${form.district}, ${form.state}. Give answer in 2 short bullet points with recommended minimum and maximum price in INR.`;
      const val = await aiService.generateResponse(prompt, 'You are LandLens Chief Revenue Valuator.');
      setAiPriceHint(val);
    } catch {
      setAiPriceHint(`Estimated market value: ₹${(Number(form.area) * 2500000).toLocaleString('en-IN')} (approx ₹25 Lakhs/Acre in ${form.district})`);
    } finally {
      setAiValuationLoading(false);
    }
  };

  const handleCreateProperty = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.title || !form.surveyNumber || !form.price || !form.area) {
      setErrorToast('Please fill in Title, Survey Number, Extent, and Price.');
      return;
    }

    setIsSubmitting(true);
    setErrorToast(null);
    setSuccessToast(null);

    try {
      const res = await propertyService.createProperty({
        title: form.title,
        category: form.category as any,
        area: Number(form.area),
        price: Number(form.price),
        description: form.description || `Verified Land Parcel listed by ${currentUser?.firstName || 'Citizen'} (${currentUser?.email || ''})`,
        surveyNumber: form.surveyNumber,
        address: form.address || `${form.village}, ${form.district}`,
        latitude: form.latitude,
        longitude: form.longitude,
        district: form.district,
        village: form.village,
        state: form.state,
        pincode: form.pincode,
        threeSixtyImageUrl: form.threeSixtyImageUrl || undefined
      });

      setSuccessToast(`Property "${form.title}" registered successfully! Survey No: ${form.surveyNumber}`);
      setForm(initialForm);
      setActiveTab('listings');
      loadMyListings();
    } catch (err: any) {
      setErrorToast(err.response?.data?.message || 'Failed to list property.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="max-w-6xl mx-auto space-y-6 pb-16">
      {/* ── TOP HERO CARD ── */}
      <div className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-950 via-slate-900 to-slate-950 border border-emerald-900/40 p-6 sm:p-8 shadow-xl text-white">
        <div className="absolute top-0 right-0 w-80 h-80 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none" />
        <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div className="space-y-2">
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 text-xs font-bold uppercase tracking-wider">
              <Sparkles className="w-3.5 h-3.5" />
              Unified Citizen Landowner & Seller Suite
            </div>
            <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight">
              Sell & Manage Land Parcels
            </h1>
            <p className="text-slate-300 text-sm max-w-2xl leading-relaxed">
              Every citizen account on LandLens can both explore/buy lands and list verified land parcels for sale. You can also initiate cryptographic land title transfers with automated Hostinger email verification to legitimate title holders.
            </p>
          </div>

          <div className="flex items-center gap-3 shrink-0">
            <button
              onClick={() => setActiveTab(activeTab === 'listings' ? 'add' : 'listings')}
              className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all shadow-lg shadow-emerald-600/30 active:scale-95"
            >
              {activeTab === 'listings' ? (
                <>
                  <Plus className="w-4 h-4" />
                  List New Land for Sale
                </>
              ) : (
                <>
                  <Home className="w-4 h-4" />
                  View My Listed Lands
                </>
              )}
            </button>
            {onExploreLands && (
              <button
                onClick={onExploreLands}
                className="px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/10 text-xs font-semibold backdrop-blur-md transition-all text-white"
              >
                Browse & Buy Lands
              </button>
            )}
          </div>
        </div>

        {/* Tab switch bar */}
        <div className="flex items-center gap-2 mt-6 pt-5 border-t border-white/10">
          <button
            onClick={() => setActiveTab('listings')}
            className={`px-4 py-1.5 rounded-lg text-xs font-bold transition-all ${
              activeTab === 'listings'
                ? 'bg-white text-slate-900 shadow-sm'
                : 'text-slate-300 hover:text-white hover:bg-white/10'
            }`}
          >
            My Listed Land Parcels ({myProperties.length})
          </button>
          <button
            onClick={() => setActiveTab('add')}
            className={`px-4 py-1.5 rounded-lg text-xs font-bold transition-all ${
              activeTab === 'add'
                ? 'bg-white text-slate-900 shadow-sm'
                : 'text-slate-300 hover:text-white hover:bg-white/10'
            }`}
          >
            ➕ Register Land for Sale
          </button>
        </div>
      </div>

      {/* Toast feedback */}
      {successToast && (
        <div className="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between">
          <div className="flex items-center gap-2 font-medium">
            <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0" />
            <span>{successToast}</span>
          </div>
          <button onClick={() => setSuccessToast(null)} className="text-emerald-500 hover:text-emerald-700">✕</button>
        </div>
      )}

      {errorToast && (
        <div className="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center justify-between">
          <div className="flex items-center gap-2 font-medium">
            <AlertCircle className="w-5 h-5 text-rose-600 shrink-0" />
            <span>{errorToast}</span>
          </div>
          <button onClick={() => setErrorToast(null)} className="text-rose-500 hover:text-rose-700">✕</button>
        </div>
      )}

      {/* ── TAB 1: MY LISTINGS ── */}
      {activeTab === 'listings' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-bold text-gray-900">Registered Land Parcels</h2>
            <button onClick={loadMyListings} className="text-xs text-blue-600 hover:underline flex items-center gap-1">
              <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} /> Refresh
            </button>
          </div>

          {loading ? (
            <div className="py-16 text-center text-gray-400">
              <RefreshCw className="w-6 h-6 animate-spin mx-auto text-emerald-600 mb-2" />
              <p className="text-xs">Loading listed land parcels...</p>
            </div>
          ) : myProperties.length === 0 ? (
            <div className="py-16 bg-white rounded-3xl border border-gray-200 text-center p-8 space-y-3 shadow-xs">
              <Home className="w-12 h-12 text-gray-300 mx-auto" />
              <h3 className="text-base font-bold text-gray-800">No Land Parcels Listed Yet</h3>
              <p className="text-xs text-gray-500 max-w-sm mx-auto">
                Ready to sell land? You can list your property with survey number and official documents for instant AI verification.
              </p>
              <button
                onClick={() => setActiveTab('add')}
                className="mt-2 px-5 py-2.5 rounded-xl bg-emerald-600 text-white font-bold text-xs hover:bg-emerald-500 shadow-sm"
              >
                List Your First Land Parcel
              </button>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
              {myProperties.map((prop) => (
                <div
                  key={prop.id}
                  className="bg-white rounded-3xl border border-gray-200/80 overflow-hidden shadow-xs hover:shadow-md transition-all flex flex-col justify-between"
                >
                  <div className="relative h-44 bg-slate-900">
                    <img
                      src={
                        prop.threeSixtyImageUrl ||
                        prop.images?.[0]?.imageUrl ||
                        'https://images.unsplash.com/photo-1500382017468-9049fed747ef?w=800&q=80'
                      }
                      alt={prop.title}
                      className="w-full h-full object-cover"
                    />
                    <div className="absolute top-3 right-3 bg-black/60 backdrop-blur-md px-2.5 py-1 rounded-full text-[10px] font-bold text-white border border-white/20">
                      {prop.category}
                    </div>
                    <div className="absolute bottom-3 left-3 bg-blue-600/90 backdrop-blur-md px-2 py-0.5 rounded-md text-[11px] font-mono font-bold text-white shadow-sm">
                      Sy: {prop.surveyNumber}
                    </div>
                  </div>

                  <div className="p-4 space-y-3 flex-1 flex flex-col justify-between">
                    <div>
                      <h3 className="font-bold text-gray-900 text-sm line-clamp-1">{prop.title}</h3>
                      <p className="text-xs text-gray-500 flex items-center gap-1 mt-0.5">
                        <MapPin className="w-3 h-3 text-gray-400" />
                        {prop.village}, {prop.district}, {prop.state}
                      </p>
                    </div>

                    <div className="flex items-center justify-between pt-2 border-t border-gray-100">
                      <div>
                        <span className="text-[10px] text-gray-400 block font-semibold">Extent</span>
                        <span className="text-xs font-bold text-gray-800">{prop.area} Acres</span>
                      </div>
                      <div className="text-right">
                        <span className="text-[10px] text-gray-400 block font-semibold">Declared Price</span>
                        <span className="text-sm font-extrabold text-emerald-600">
                          ₹{Number(prop.price).toLocaleString('en-IN')}
                        </span>
                      </div>
                    </div>

                    {/* Quick Transfer CTA Button */}
                    <button
                      onClick={() => onInitiateTransfer?.(prop.id)}
                      className="w-full mt-2 py-2 px-3 rounded-xl bg-blue-50 hover:bg-blue-100 border border-blue-200 text-blue-700 font-bold text-xs flex items-center justify-center gap-1.5 transition-all"
                    >
                      <ShieldCheck className="w-4 h-4 text-blue-600" />
                      Transfer Title to Buyer
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* ── TAB 2: REGISTER LAND FORM ── */}
      {activeTab === 'add' && (
        <div className="bg-white rounded-3xl border border-gray-200/80 p-6 sm:p-8 shadow-xs">
          <div className="flex items-center justify-between pb-4 mb-6 border-b border-gray-100">
            <div>
              <h2 className="text-lg font-bold text-gray-900">List Land Parcel for Sale</h2>
              <p className="text-xs text-gray-500">Provide revenue survey records and property details</p>
            </div>
            <button
              onClick={handleAiValuation}
              disabled={aiValuationLoading}
              className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-purple-50 text-purple-700 hover:bg-purple-100 text-xs font-bold border border-purple-200 transition-all"
            >
              <Sparkles className={`w-3.5 h-3.5 ${aiValuationLoading ? 'animate-spin' : ''}`} />
              AI Price Valuation
            </button>
          </div>

          {aiPriceHint && (
            <div className="mb-6 p-4 rounded-2xl bg-purple-50/80 border border-purple-200 text-purple-900 text-xs space-y-1">
              <span className="font-bold uppercase tracking-wider text-purple-700 flex items-center gap-1">
                <Sparkles className="w-3.5 h-3.5" /> LandLens AI Valuation Estimate
              </span>
              <p className="whitespace-pre-line leading-relaxed">{aiPriceHint}</p>
            </div>
          )}

          <form onSubmit={handleCreateProperty} className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">
                  Property Title *
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Green Valley Farm Lands"
                  value={form.title}
                  onChange={(e) => setForm({ ...form, title: e.target.value })}
                  className="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm text-gray-900 focus:ring-2 focus:ring-emerald-500 focus:bg-white"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">
                  Category *
                </label>
                <select
                  value={form.category}
                  onChange={(e) => setForm({ ...form, category: e.target.value as any })}
                  className="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm text-gray-900 focus:ring-2 focus:ring-emerald-500 focus:bg-white"
                >
                  <option value="AGRICULTURAL">Agricultural Land</option>
                  <option value="RESIDENTIAL">Residential Plot / Zone</option>
                  <option value="COMMERCIAL">Commercial Real Estate</option>
                  <option value="INDUSTRIAL">Industrial Land</option>
                </select>
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">
                  Survey Number *
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. 104/2B"
                  value={form.surveyNumber}
                  onChange={(e) => setForm({ ...form, surveyNumber: e.target.value })}
                  className="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm text-gray-900 focus:ring-2 focus:ring-emerald-500 focus:bg-white font-mono"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">
                  Land Extent (Acres) *
                </label>
                <input
                  type="number"
                  step="0.01"
                  required
                  placeholder="2.5"
                  value={form.area}
                  onChange={(e) => setForm({ ...form, area: e.target.value })}
                  className="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm text-gray-900 focus:ring-2 focus:ring-emerald-500 focus:bg-white"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">
                  Selling Price (₹) *
                </label>
                <input
                  type="number"
                  required
                  placeholder="3500000"
                  value={form.price}
                  onChange={(e) => setForm({ ...form, price: e.target.value })}
                  className="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm text-gray-900 focus:ring-2 focus:ring-emerald-500 focus:bg-white"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-4 gap-3">
              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">Village</label>
                <input
                  type="text"
                  value={form.village}
                  onChange={(e) => setForm({ ...form, village: e.target.value })}
                  className="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm text-gray-900"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">District</label>
                <input
                  type="text"
                  value={form.district}
                  onChange={(e) => setForm({ ...form, district: e.target.value })}
                  className="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm text-gray-900"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">State</label>
                <input
                  type="text"
                  value={form.state}
                  onChange={(e) => setForm({ ...form, state: e.target.value })}
                  className="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm text-gray-900"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">Pincode</label>
                <input
                  type="text"
                  value={form.pincode}
                  onChange={(e) => setForm({ ...form, pincode: e.target.value })}
                  className="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm text-gray-900"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-1">
                360 Virtual Tour URL or Photo Link (Optional)
              </label>
              <input
                type="url"
                placeholder="https://kuula.co/share/... or Cloudinary image URL"
                value={form.threeSixtyImageUrl}
                onChange={(e) => setForm({ ...form, threeSixtyImageUrl: e.target.value })}
                className="w-full bg-gray-50 border border-gray-200 rounded-xl py-2.5 px-3 text-sm text-gray-900"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-1">Description</label>
              <textarea
                rows={2}
                placeholder="Provide access road details, water availability, soil type..."
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
                className="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs text-gray-900"
              />
            </div>

            <button
              type="submit"
              disabled={isSubmitting}
              className="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-sm shadow-lg shadow-emerald-600/20 active:scale-98 transition-all flex items-center justify-center gap-2 disabled:opacity-50"
            >
              {isSubmitting ? (
                <>
                  <RefreshCw className="w-4 h-4 animate-spin" />
                  Registering Land Parcel...
                </>
              ) : (
                <>
                  <Plus className="w-4 h-4" />
                  Register Land for Sale
                </>
              )}
            </button>
          </form>
        </div>
      )}
    </div>
  );
};
