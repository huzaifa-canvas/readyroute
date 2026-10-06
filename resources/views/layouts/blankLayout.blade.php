@isset($pageConfigs)
  {!! Helper::updatePageConfig($pageConfigs) !!}
@endisset

@php
  $configData = Helper::appClasses();

  /* Display elements */
  $customizerHidden = $customizerHidden ?? '';
@endphp

@extends('layouts/commonMaster')

@section('layoutContent')
  <!-- Content -->
  @yield('content')
  <!--/ Content -->

  @include('_partials._csrf-refresh')
@endsection
