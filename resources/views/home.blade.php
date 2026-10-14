@extends('layouts.guest')

@section('title', 'Laundry Coin - Self Laundry & Laundry Drop-Off Cashless')

@section('content')
    <!-- Hero Section -->
    <x-sections.hero />

    <!-- Layanan & Tarif Section (Dua Grup: Self & Non-Self, Kapasitas Mesin) -->
    <x-sections.layanan :services="$services ?? null" />

    <!-- Order Tracking Realtime Section -->
    <x-sections.order-tracking />

    <!-- 4 Langkah Cara Kerja -->
    <x-sections.cara-kerja />

    <!-- Lokasi & Jam Operasional -->
    <x-sections.area />

    <!-- Pertanyaan Umum (FAQ) Accordion -->
    <x-sections.faq />

    <!-- Kontak & WhatsApp Section -->
    <x-sections.contact />
@endsection
