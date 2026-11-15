import {
    createIcons, ArrowLeft, ArrowRight, Ban, Banknote, Bell, Building, Building2, CalendarCheck, CalendarDays, CalendarX,
    Camera, Check, ChevronDown, ChevronLeft, ChevronRight, CircleAlert, CircleCheck, CircleX, Clock, CreditCard,
    ExternalLink, Eye, EyeOff, Filter, Flag, Heart, Hourglass, Image, ImagePlus, Info, Laptop, LayoutDashboard,
    LayoutGrid, LogOut, Mail, MapPin, Menu, MessageCircle, MessageSquare, Mic, Minus, PartyPopper, Pencil, Phone,
    PhoneCall, Plus, Receipt, RefreshCw, Reply, Search, SearchX, Send, Settings, ShieldCheck, SlidersHorizontal,
    Sparkles, Star, Trash2, TrendingUp, TriangleAlert, Undo2, Upload, User, Users, Wallet, X, Briefcase, BadgeCheck,
    Copy, ListChecks, Share2, Wifi, DoorOpen, Lock, Zap,
} from 'lucide';
import './ui.js';
import './booking-widget.js';
import './map.js';
import './chatbot.js';
import './sdgs.js';

const icons = {
    ArrowLeft, ArrowRight, Ban, Banknote, Bell, Building, Building2, CalendarCheck, CalendarDays, CalendarX, Camera, Check,
    ChevronDown, ChevronLeft, ChevronRight, CircleAlert, CircleCheck, CircleX, Clock, CreditCard, ExternalLink, Eye, EyeOff,
    Filter, Flag, Heart, Hourglass, Image, ImagePlus, Info, Laptop, LayoutDashboard, LayoutGrid, LogOut, Mail, MapPin, Menu,
    MessageCircle, MessageSquare, Mic, Minus, PartyPopper, Pencil, Phone, PhoneCall, Plus, Receipt, RefreshCw, Reply,
    Search, SearchX, Send, Settings, ShieldCheck, SlidersHorizontal, Sparkles, Star, Trash2, TrendingUp, TriangleAlert,
    Undo2, Upload, User, Users, Wallet, X, Briefcase, BadgeCheck, Copy, ListChecks, Share2, Wifi, DoorOpen, Lock, Zap,
};

const render = () => createIcons({ icons, attrs: { 'stroke-width': 1.75 } });
window.renderIcons = render;
document.addEventListener('DOMContentLoaded', render);
