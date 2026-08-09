<?php
use PhpOffice\PhpSpreadsheet\IOFactory;

// Hata raporlama — production'da kapatın
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// ---- DB + Auth ----
$dbError = null;
try {
    DB::get();

    // 1. Temel tabloları oluştur (CREATE TABLE IF NOT EXISTS)
    $sql = file_exists(__DIR__ . '/install.sql')
        ? file_get_contents(__DIR__ . '/install.sql')
        : base64_decode('LS0gPT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09Ci0tICBUcmVuZHlvbCBLYXIvWmFyYXIgQW5hbGl6IFNpc3RlbWkg4oCTIE15U1FMIFNjaGVtYSB2MwotLSA9PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT0KCkNSRUFURSBEQVRBQkFTRSBJRiBOT1QgRVhJU1RTIGB0cmVuZHlvbF9hbmFsaXpgCiAgQ0hBUkFDVEVSIFNFVCB1dGY4bWI0IENPTExBVEUgdXRmOG1iNF91bmljb2RlX2NpOwpVU0UgYHRyZW5keW9sX2FuYWxpemA7CgotLSAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tCi0tICBLdWxsYW7EsWPEsWxhcgotLSAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tCkNSRUFURSBUQUJMRSBJRiBOT1QgRVhJU1RTIGBrdWxsYW5pY2lsYXJgICgKICAgIGBpZGAgICAgICAgICAgICBJTlQgVU5TSUdORUQgQVVUT19JTkNSRU1FTlQgUFJJTUFSWSBLRVksCiAgICBgZW1haWxgICAgICAgICAgVkFSQ0hBUigxNTApIFVOSVFVRSBOT1QgTlVMTCwKICAgIGBzaWZyZWAgICAgICAgICBWQVJDSEFSKDI1NSkgTk9UIE5VTEwsCiAgICBgYWRfc295YWRgICAgICAgVkFSQ0hBUigxMDApLAogICAgYHJvbGAgICAgICAgICAgIEVOVU0oJ2FkbWluJywndXllJykgREVGQVVMVCAndXllJywKICAgIGBha3RpZmAgICAgICAgICBUSU5ZSU5UKDEpIERFRkFVTFQgMSwKICAgIGBrYXlpdF90YXJpaGlgICBEQVRFVElNRSBERUZBVUxUIENVUlJFTlRfVElNRVNUQU1QLAogICAgSU5ERVggYGlkeF9lbWFpbGAgKGBlbWFpbGApCikgRU5HSU5FPUlubm9EQiBERUZBVUxUIENIQVJTRVQ9dXRmOG1iNDsKCi0tIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0KLS0gIE1hxJ9hemFsYXIgKGhlciBrdWxsYW7EsWPEsW7EsW4gYmlyZGVuIGZhemxhIG1hxJ9hemFzxLEgb2xhYmlsaXIpCi0tIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0KQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYG1hZ2F6YWxhcmAgKAogICAgYGlkYCAgICAgICAgICAgICBJTlQgVU5TSUdORUQgQVVUT19JTkNSRU1FTlQgUFJJTUFSWSBLRVksCiAgICBga3VsbGFuaWNpX2lkYCAgIElOVCBVTlNJR05FRCBOT1QgTlVMTCwKICAgIGBtYWdhemFfYWRpYCAgICAgVkFSQ0hBUigxNTApIE5PVCBOVUxMLAogICAgYHR5X3NlbGxlcl9pZGAgICBWQVJDSEFSKDUwKSwKICAgIGB0eV9hcGlfa2V5YCAgICAgVkFSQ0hBUigyMDApLAogICAgYHR5X2FwaV9zZWNyZXRgICBWQVJDSEFSKDIwMCksCiAgICBgYWt0aWZgICAgICAgICAgIFRJTllJTlQoMSkgREVGQVVMVCAxLAogICAgYG9sdXN0dXJtYWAgICAgICBEQVRFVElNRSBERUZBVUxUIENVUlJFTlRfVElNRVNUQU1QLAogICAgRk9SRUlHTiBLRVkgKGBrdWxsYW5pY2lfaWRgKSBSRUZFUkVOQ0VTIGBrdWxsYW5pY2lsYXJgKGBpZGApIE9OIERFTEVURSBDQVNDQURFLAogICAgSU5ERVggYGlkeF9rdWxsYW5pY2lgIChga3VsbGFuaWNpX2lkYCkKKSBFTkdJTkU9SW5ub0RCIERFRkFVTFQgQ0hBUlNFVD11dGY4bWI0OwoKLS0gLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQotLSAgU2lwYXJpxZ8ga2F5xLF0bGFyxLEKLS0gLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQpDUkVBVEUgVEFCTEUgSUYgTk9UIEVYSVNUUyBgc2lwYXJpc2xlcmAgKAogICAgYGlkYCAgICAgICAgICAgICAgICAgICBJTlQgVU5TSUdORUQgQVVUT19JTkNSRU1FTlQgUFJJTUFSWSBLRVksCiAgICBgbWFnYXphX2lkYCAgICAgICAgICAgIElOVCBVTlNJR05FRCBOT1QgTlVMTCwKICAgIGBzaXBhcmlzX3RhcmloaWAgICAgICAgVkFSQ0hBUigzMCksCiAgICBgc2lwYXJpc19ub2AgICAgICAgICAgIFZBUkNIQVIoNTApLAogICAgYHVsa2VgICAgICAgICAgICAgICAgICBWQVJDSEFSKDUwKSwKICAgIGBzaXBhcmlzX3N0YXR1c3VgICAgICAgVkFSQ0hBUig2MCksCiAgICBgc2lya2V0YCAgICAgICAgICAgICAgIFZBUkNIQVIoNjApLAogICAgYG9kZW1lX3lvbnRlbWlgICAgICAgICBWQVJDSEFSKDYwKSwKICAgIGBtdXN0ZXJpYCAgICAgICAgICAgICAgVkFSQ0hBUigxMDApLAogICAgYHVydW5fYWRlZGlgICAgICAgICAgICBERUNJTUFMKDEwLDIpIERFRkFVTFQgMCwKICAgIGBzaXBhcmlzX3R1dGFyaWAgICAgICAgREVDSU1BTCgxMiwyKSBERUZBVUxUIDAsCiAgICBga29taXN5b25gICAgICAgICAgICAgIERFQ0lNQUwoMTIsMikgREVGQVVMVCAwLAogICAgYGluZGlyaW1gICAgICAgICAgICAgICBERUNJTUFMKDEyLDIpIERFRkFVTFQgMCwKICAgIGBnb25kZXJpX2thcmdvYCAgICAgICAgREVDSU1BTCgxMiwyKSBERUZBVUxUIDAsCiAgICBgaWFkZV9rYXJnb2AgICAgICAgICAgIERFQ0lNQUwoMTIsMikgREVGQVVMVCAwLAogICAgYGNlemFgICAgICAgICAgICAgICAgICBERUNJTUFMKDEyLDIpIERFRkFVTFQgMCwKICAgIGBpcHRhbGAgICAgICAgICAgICAgICAgREVDSU1BTCgxMiwyKSBERUZBVUxUIDAsCiAgICBgaWFkZWAgICAgICAgICAgICAgICAgIERFQ0lNQUwoMTIsMikgREVGQVVMVCAwLAogICAgYGRpZ2VyYCAgICAgICAgICAgICAgICBERUNJTUFMKDEyLDIpIERFRkFVTFQgMCwKICAgIGBuZXRfdHV0YXJgICAgICAgICAgICAgREVDSU1BTCgxMiwyKSBERUZBVUxUIDAsCiAgICBgcGxhdGZvcm1faGl6bWV0YCAgICAgIERFQ0lNQUwoMTIsMikgREVGQVVMVCAwLAogICAgYHl1a2xlbWVfdGFyaWhpYCAgICAgICBEQVRFVElNRSwKICAgIGB0eV91cnVuX2lkYCAgICAgICAgICAgVkFSQ0hBUig2MCkgTlVMTCwKICAgIGB0eV9iYXJjb2RlYCAgICAgICAgICAgVkFSQ0hBUigxMDApIE5VTEwsCiAgICBgbXVzdGVyaV90YW1fYWRgICAgICAgIFZBUkNIQVIoMTAwKSwKICAgIGBrYXJnb190YWtpcF9ub2AgICAgICAgVkFSQ0hBUig1MCksCiAgICBga2FyZ29fZmlybWFzaWAgICAgICAgIFZBUkNIQVIoNjApLAogICAgYGFwaV9zdGF0dXN1YCAgICAgICAgICBWQVJDSEFSKDQwKSwKICAgIGBzaGlwbWVudF9wYWNrYWdlX2lkYCAgVkFSQ0hBUig1MCksCiAgICBgYXBpX3Nvbl9ndW5jZWxsZW1lYCAgIERBVEVUSU1FLAogICAgVU5JUVVFIEtFWSBgdWtfbWFnYXphX3NpcGFyaXNgIChgbWFnYXphX2lkYCwgYHNpcGFyaXNfbm9gKSwKICAgIElOREVYIGBpZHhfbWFnYXphYCAgKGBtYWdhemFfaWRgKSwKICAgIElOREVYIGBpZHhfdHlfdXJ1bmAgKGB0eV91cnVuX2lkYCksCiAgICBJTkRFWCBgaWR4X3RhcmloYCAgIChgc2lwYXJpc190YXJpaGlgKSwKICAgIEZPUkVJR04gS0VZIChgbWFnYXphX2lkYCkgUkVGRVJFTkNFUyBgbWFnYXphbGFyYChgaWRgKSBPTiBERUxFVEUgQ0FTQ0FERQopIEVOR0lORT1Jbm5vREIgREVGQVVMVCBDSEFSU0VUPXV0ZjhtYjQ7CgotLSAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tCi0tICBTYXTEscWfIHJhcG9ydQotLSAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tCkNSRUFURSBUQUJMRSBJRiBOT1QgRVhJU1RTIGB1cnVuX3NhdGlzYCAoCiAgICBgaWRgICAgICAgICAgICAgICAgICAgIElOVCBVTlNJR05FRCBBVVRPX0lOQ1JFTUVOVCBQUklNQVJZIEtFWSwKICAgIGBtYWdhemFfaWRgICAgICAgICAgICAgSU5UIFVOU0lHTkVEIE5PVCBOVUxMLAogICAgYGJhcmtvZGAgICAgICAgICAgICAgICBWQVJDSEFSKDEwMCksCiAgICBgdXJ1bl9hZGlgICAgICAgICAgICAgIFZBUkNIQVIoMzAwKSwKICAgIGBtb2RlbF9rb2R1YCAgICAgICAgICAgVkFSQ0hBUigxMDApLAogICAgYGthdGVnb3JpYCAgICAgICAgICAgICBWQVJDSEFSKDEwMCksCiAgICBgbWFya2FgICAgICAgICAgICAgICAgIFZBUkNIQVIoMTAwKSwKICAgIGByZW5rYCAgICAgICAgICAgICAgICAgVkFSQ0hBUig2MCksCiAgICBgYmVkZW5gICAgICAgICAgICAgICAgIFZBUkNIQVIoNjApLAogICAgYGJydXRfc2lwYXJpc2AgICAgICAgICBERUNJTUFMKDEwLDIpIERFRkFVTFQgMCwKICAgIGBicnV0X3NhdGlzYCAgICAgICAgICAgREVDSU1BTCgxMCwyKSBERUZBVUxUIDAsCiAgICBgaXB0YWxfYWRlZGlgICAgICAgICAgIERFQ0lNQUwoMTAsMikgREVGQVVMVCAwLAogICAgYGlwdGFsX29yYW5pYCAgICAgICAgICBERUNJTUFMKDgsNCkgIERFRkFVTFQgMCwKICAgIGBpYWRlX2FkZWRpYCAgICAgICAgICAgREVDSU1BTCgxMCwyKSBERUZBVUxUIDAsCiAgICBgaWFkZV9vcmFuaWAgICAgICAgICAgIERFQ0lNQUwoOCw0KSAgREVGQVVMVCAwLAogICAgYG5ldF9zYXRpc2AgICAgICAgICAgICBERUNJTUFMKDEwLDIpIERFRkFVTFQgMCwKICAgIGBicnV0X2Npcm9gICAgICAgICAgICAgREVDSU1BTCgxNCwyKSBERUZBVUxUIDAsCiAgICBgaW5kaXJpbV90dXRhcmlgICAgICAgIERFQ0lNQUwoMTIsMikgREVGQVVMVCAwLAogICAgYG5ldF9jaXJvYCAgICAgICAgICAgICBERUNJTUFMKDE0LDIpIERFRkFVTFQgMCwKICAgIGB0b3BsYW1fa29taXN5b25gICAgICAgREVDSU1BTCgxMiwyKSBERUZBVUxUIDAsCiAgICBgb3J0X2tvbWlzeW9uYCAgICAgICAgIERFQ0lNQUwoMTAsMikgREVGQVVMVCAwLAogICAgYG9ydF9rb21pc3lvbl9vcmFuaWAgICBERUNJTUFMKDgsNCkgIERFRkFVTFQgMCwKICAgIGBvcnRfc2F0aXNfZml5YXRpYCAgICAgREVDSU1BTCgxMCwyKSBERUZBVUxUIDAsCiAgICBgZ3VuY2VsX2ZpeWF0YCAgICAgICAgIERFQ0lNQUwoMTAsMikgREVGQVVMVCAwLAogICAgYGd1bmNlbF9zdG9rYCAgICAgICAgICBERUNJTUFMKDEwLDIpIERFRkFVTFQgMCwKICAgIGB5dWtsZW1lX3RhcmloaWAgICAgICAgREFURVRJTUUsCiAgICBgdHlfdXJ1bl9pZGAgICAgICAgICAgIFZBUkNIQVIoNjApIE5VTEwsCiAgICBJTkRFWCBgaWR4X21hZ2F6YWAgIChgbWFnYXphX2lkYCksCiAgICBJTkRFWCBgaWR4X2JhcmtvZGAgIChgYmFya29kYCksCiAgICBGT1JFSUdOIEtFWSAoYG1hZ2F6YV9pZGApIFJFRkVSRU5DRVMgYG1hZ2F6YWxhcmAoYGlkYCkgT04gREVMRVRFIENBU0NBREUKKSBFTkdJTkU9SW5ub0RCIERFRkFVTFQgQ0hBUlNFVD11dGY4bWI0OwoKLS0gLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQotLSAgVHJlbmR5b2wgQVBJIMO8csO8bmxlcmkKLS0gLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQpDUkVBVEUgVEFCTEUgSUYgTk9UIEVYSVNUUyBgdHJlbmR5b2xfdXJ1bmxlcmAgKAogICAgYGlkYCAgICAgICAgICAgICAgIElOVCBVTlNJR05FRCBBVVRPX0lOQ1JFTUVOVCBQUklNQVJZIEtFWSwKICAgIGBtYWdhemFfaWRgICAgICAgICBJTlQgVU5TSUdORUQgTk9UIE5VTEwsCiAgICBgdHlfaWRgICAgICAgICAgICAgVkFSQ0hBUig2MCksCiAgICBgYmFyY29kZWAgICAgICAgICAgVkFSQ0hBUigxMDApLAogICAgYHN0b2NrX2NvZGVgICAgICAgIFZBUkNIQVIoMTAwKSwKICAgIGBwcm9kdWN0X21haW5faWRgICBWQVJDSEFSKDEwMCksCiAgICBgcHJvZHVjdF9jb2RlYCAgICAgVkFSQ0hBUig2MCksCiAgICBgdGl0bGVgICAgICAgICAgICAgVkFSQ0hBUigzMDApLAogICAgYGNhdGVnb3J5X25hbWVgICAgIFZBUkNIQVIoMTUwKSwKICAgIGBicmFuZGAgICAgICAgICAgICBWQVJDSEFSKDEwMCksCiAgICBgY29sb3JgICAgICAgICAgICAgVkFSQ0hBUig2MCksCiAgICBgc2l6ZWAgICAgICAgICAgICAgVkFSQ0hBUig2MCksCiAgICBgbGlzdF9wcmljZWAgICAgICAgREVDSU1BTCgxMCwyKSBERUZBVUxUIDAsCiAgICBgc2FsZV9wcmljZWAgICAgICAgREVDSU1BTCgxMCwyKSBERUZBVUxUIDAsCiAgICBgcXVhbnRpdHlgICAgICAgICAgSU5UIERFRkFVTFQgMCwKICAgIGBhcHByb3ZlZGAgICAgICAgICBUSU5ZSU5UKDEpIERFRkFVTFQgMCwKICAgIGBpbWFnZV91cmxgICAgICAgICBWQVJDSEFSKDUwMCksCiAgICBgcHJvZHVjdF91cmxgICAgICAgVkFSQ0hBUig1MDApLAogICAgYHNvbl9ndW5jZWxsZW1lYCAgIERBVEVUSU1FLAogICAgYGNla21lX3RhcmloaWAgICAgIERBVEVUSU1FLAogICAgVU5JUVVFIEtFWSBgdWtfbWFnYXphX3R5YCAoYG1hZ2F6YV9pZGAsIGB0eV9pZGApLAogICAgSU5ERVggYGlkeF9tYWdhemFgICAgICAoYG1hZ2F6YV9pZGApLAogICAgSU5ERVggYGlkeF9iYXJjb2RlYCAgICAoYGJhcmNvZGVgKSwKICAgIElOREVYIGBpZHhfc3RvY2tfY29kZWAgKGBzdG9ja19jb2RlYCksCiAgICBGT1JFSUdOIEtFWSAoYG1hZ2F6YV9pZGApIFJFRkVSRU5DRVMgYG1hZ2F6YWxhcmAoYGlkYCkgT04gREVMRVRFIENBU0NBREUKKSBFTkdJTkU9SW5ub0RCIERFRkFVTFQgQ0hBUlNFVD11dGY4bWI0OwoKLS0gLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQotLSAgTWFsaXlldGxlcgotLSAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tCkNSRUFURSBUQUJMRSBJRiBOT1QgRVhJU1RTIGBtYWxpeWV0bGVyYCAoCiAgICBgaWRgICAgICAgICAgICAgICAgSU5UIFVOU0lHTkVEIEFVVE9fSU5DUkVNRU5UIFBSSU1BUlkgS0VZLAogICAgYG1hZ2F6YV9pZGAgICAgICAgIElOVCBVTlNJR05FRCBOT1QgTlVMTCwKICAgIGB0eV91cnVuX2lkYCAgICAgICBWQVJDSEFSKDYwKSwKICAgIGBiYXJjb2RlYCAgICAgICAgICBWQVJDSEFSKDEwMCksCiAgICBgdXJ1bl9hZGlgICAgICAgICAgVkFSQ0hBUigzMDApLAogICAgYGJpcmltX21hbGl5ZXRgICAgIERFQ0lNQUwoMTAsMikgREVGQVVMVCAwLAogICAgYGthcmdvX21hbGl5ZXRpYCAgIERFQ0lNQUwoMTAsMikgREVGQVVMVCAwLAogICAgYHBha2V0X21hbGl5ZXRpYCAgIERFQ0lNQUwoMTAsMikgREVGQVVMVCAwLAogICAgYGRpZ2VyX21hbGl5ZXRgICAgIERFQ0lNQUwoMTAsMikgREVGQVVMVCAwLAogICAgYGd1bmNlbGxlbWVgICAgICAgIERBVEVUSU1FLAogICAgVU5JUVVFIEtFWSBgdWtfbWFnYXphX3VydW5gIChgbWFnYXphX2lkYCwgYHR5X3VydW5faWRgKSwKICAgIElOREVYIGBpZHhfbWFnYXphYCAgIChgbWFnYXphX2lkYCksCiAgICBJTkRFWCBgaWR4X3R5X3VydW5gICAoYHR5X3VydW5faWRgKSwKICAgIEZPUkVJR04gS0VZIChgbWFnYXphX2lkYCkgUkVGRVJFTkNFUyBgbWFnYXphbGFyYChgaWRgKSBPTiBERUxFVEUgQ0FTQ0FERQopIEVOR0lORT1Jbm5vREIgREVGQVVMVCBDSEFSU0VUPXV0ZjhtYjQ7CgotLSAtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tCi0tICBTaXBhcmnFnyBzYXTEsXIga2FsZW1sZXJpCi0tIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0KQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYHNpcGFyaXNfc2F0aXJsYXJpYCAoCiAgICBgaWRgICAgICAgICAgICAgICAgICAgSU5UIFVOU0lHTkVEIEFVVE9fSU5DUkVNRU5UIFBSSU1BUlkgS0VZLAogICAgYG1hZ2F6YV9pZGAgICAgICAgICAgIElOVCBVTlNJR05FRCBOT1QgTlVMTCwKICAgIGBzaXBhcmlzX25vYCAgICAgICAgICBWQVJDSEFSKDUwKSwKICAgIGBzaGlwbWVudF9wYWNrYWdlX2lkYCBWQVJDSEFSKDUwKSwKICAgIGBiYXJjb2RlYCAgICAgICAgICAgICBWQVJDSEFSKDEwMCksCiAgICBgc3RvY2tfY29kZWAgICAgICAgICAgVkFSQ0hBUigxMDApLAogICAgYHByb2R1Y3RfbmFtZWAgICAgICAgIFZBUkNIQVIoMzAwKSwKICAgIGBhZGV0YCAgICAgICAgICAgICAgICBJTlQgREVGQVVMVCAxLAogICAgYGJpcmltX2ZpeWF0YCAgICAgICAgIERFQ0lNQUwoMTAsMikgREVGQVVMVCAwLAogICAgYGthbGVtX3R1dGFyaWAgICAgICAgIERFQ0lNQUwoMTIsMikgREVGQVVMVCAwLAogICAgYHR5X3VydW5faWRgICAgICAgICAgIFZBUkNIQVIoNjApIE5VTEwsCiAgICBJTkRFWCBgaWR4X21hZ2F6YWAgICAgKGBtYWdhemFfaWRgKSwKICAgIElOREVYIGBpZHhfc2lwbm9gICAgICAoYHNpcGFyaXNfbm9gKSwKICAgIElOREVYIGBpZHhfYmFyY29kZWAgICAoYGJhcmNvZGVgKSwKICAgIEZPUkVJR04gS0VZIChgbWFnYXphX2lkYCkgUkVGRVJFTkNFUyBgbWFnYXphbGFyYChgaWRgKSBPTiBERUxFVEUgQ0FTQ0FERQopIEVOR0lORT1Jbm5vREIgREVGQVVMVCBDSEFSU0VUPXV0ZjhtYjQ7CgotLSA9PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT0KLS0gIE3EsEdSQVNZT046IE1ldmN1dCB0YWJsb2xhcmEgbWFnYXphX2lkIGVrbGUKLS0gIChDUkVBVEUgSUYgTk9UIEVYSVNUUyB5ZW5pIHPDvHR1bmxhcsSxIGVrbGVtZXosIEFMVEVSIGdlcmVraXIpCi0tID09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PQoKLS0gVmFyc2F5xLFsYW4gbWHEn2F6YSBvbHXFn3R1ciAoaWQ9MSkg4oCUIG1ldmN1dCB2ZXJpbGVyIGJ1cmF5YSBiYcSfbGFuxLFyCklOU0VSVCBJR05PUkUgSU5UTyBga3VsbGFuaWNpbGFyYCAoaWQsIGVtYWlsLCBzaWZyZSwgYWRfc295YWQsIHJvbCwgYWt0aWYpCiAgVkFMVUVTICgxLCAnYWRtaW5AbG9jYWxob3N0JywgJyQyeSQxMCRkdW1teS5oYXNoLnBsYWNlaG9sZGVyLm5vdC52YWxpZCcsICdTaXN0ZW0gQWRtaW4nLCAnYWRtaW4nLCAxKTsKCklOU0VSVCBJR05PUkUgSU5UTyBgbWFnYXphbGFyYCAoaWQsIGt1bGxhbmljaV9pZCwgbWFnYXphX2FkaSwgYWt0aWYpCiAgVkFMVUVTICgxLCAxLCAnVmFyc2F5xLFsYW4gTWHEn2F6YScsIDEpOwoKLS0gc2lwYXJpc2xlciB0YWJsb3N1bmEgbWFnYXphX2lkIGVrbGUKQUxURVIgVEFCTEUgYHNpcGFyaXNsZXJgCiAgQUREIENPTFVNTiBJRiBOT1QgRVhJU1RTIGBtYWdhemFfaWRgIElOVCBVTlNJR05FRCBOT1QgTlVMTCBERUZBVUxUIDEgRklSU1QsCiAgQUREIENPTFVNTiBJRiBOT1QgRVhJU1RTIGBtdXN0ZXJpX3RhbV9hZGAgICAgICBWQVJDSEFSKDEwMCksCiAgQUREIENPTFVNTiBJRiBOT1QgRVhJU1RTIGBrYXJnb190YWtpcF9ub2AgICAgICBWQVJDSEFSKDUwKSwKICBBREQgQ09MVU1OIElGIE5PVCBFWElTVFMgYGthcmdvX2Zpcm1hc2lgICAgICAgIFZBUkNIQVIoNjApLAogIEFERCBDT0xVTU4gSUYgTk9UIEVYSVNUUyBgYXBpX3N0YXR1c3VgICAgICAgICAgVkFSQ0hBUig0MCksCiAgQUREIENPTFVNTiBJRiBOT1QgRVhJU1RTIGBzaGlwbWVudF9wYWNrYWdlX2lkYCBWQVJDSEFSKDUwKSwKICBBREQgQ09MVU1OIElGIE5PVCBFWElTVFMgYGFwaV9zb25fZ3VuY2VsbGVtZWAgIERBVEVUSU1FOwoKLS0gdXJ1bl9zYXRpcyB0YWJsb3N1bmEgbWFnYXphX2lkIGVrbGUKQUxURVIgVEFCTEUgYHVydW5fc2F0aXNgCiAgQUREIENPTFVNTiBJRiBOT1QgRVhJU1RTIGBtYWdhemFfaWRgIElOVCBVTlNJR05FRCBOT1QgTlVMTCBERUZBVUxUIDEgRklSU1Q7CgotLSB0cmVuZHlvbF91cnVubGVyIHRhYmxvc3VuYSBtYWdhemFfaWQgZWtsZQpBTFRFUiBUQUJMRSBgdHJlbmR5b2xfdXJ1bmxlcmAKICBBREQgQ09MVU1OIElGIE5PVCBFWElTVFMgYG1hZ2F6YV9pZGAgSU5UIFVOU0lHTkVEIE5PVCBOVUxMIERFRkFVTFQgMSBGSVJTVDsKCi0tIG1hbGl5ZXRsZXIgdGFibG9zdW5hIG1hZ2F6YV9pZCBla2xlCkFMVEVSIFRBQkxFIGBtYWxpeWV0bGVyYAogIEFERCBDT0xVTU4gSUYgTk9UIEVYSVNUUyBgbWFnYXphX2lkYCBJTlQgVU5TSUdORUQgTk9UIE5VTEwgREVGQVVMVCAxIEZJUlNUOwoKLS0gc2lwYXJpc19zYXRpcmxhcmkgdGFibG9zdW5hIG1hZ2F6YV9pZCBla2xlICh0YWJsbyB5b2tzYSBDUkVBVEUgaWxlIG9sdcWfdHVydWx1cikKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYHNpcGFyaXNfc2F0aXJsYXJpYCAoCiAgICBgaWRgICAgICAgICAgICAgICAgICAgSU5UIFVOU0lHTkVEIEFVVE9fSU5DUkVNRU5UIFBSSU1BUlkgS0VZLAogICAgYG1hZ2F6YV9pZGAgICAgICAgICAgIElOVCBVTlNJR05FRCBOT1QgTlVMTCBERUZBVUxUIDEsCiAgICBgc2lwYXJpc19ub2AgICAgICAgICAgVkFSQ0hBUig1MCksCiAgICBgc2hpcG1lbnRfcGFja2FnZV9pZGAgVkFSQ0hBUig1MCksCiAgICBgYmFyY29kZWAgICAgICAgICAgICAgVkFSQ0hBUigxMDApLAogICAgYHN0b2NrX2NvZGVgICAgICAgICAgIFZBUkNIQVIoMTAwKSwKICAgIGBwcm9kdWN0X25hbWVgICAgICAgICBWQVJDSEFSKDMwMCksCiAgICBgYWRldGAgICAgICAgICAgICAgICAgSU5UIERFRkFVTFQgMSwKICAgIGBiaXJpbV9maXlhdGAgICAgICAgICBERUNJTUFMKDEwLDIpIERFRkFVTFQgMCwKICAgIGBrYWxlbV90dXRhcmlgICAgICAgICBERUNJTUFMKDEyLDIpIERFRkFVTFQgMCwKICAgIGB0eV91cnVuX2lkYCAgICAgICAgICBWQVJDSEFSKDYwKSBOVUxMLAogICAgSU5ERVggYGlkeF9tYWdhemFgICAgIChgbWFnYXphX2lkYCksCiAgICBJTkRFWCBgaWR4X3NpcG5vYCAgICAgKGBzaXBhcmlzX25vYCkKKSBFTkdJTkU9SW5ub0RCIERFRkFVTFQgQ0hBUlNFVD11dGY4bWI0OwoKQUxURVIgVEFCTEUgYHNpcGFyaXNfc2F0aXJsYXJpYAogIEFERCBDT0xVTU4gSUYgTk9UIEVYSVNUUyBgbWFnYXphX2lkYCBJTlQgVU5TSUdORUQgTk9UIE5VTEwgREVGQVVMVCAxOwoKLS0gTWV2Y3V0IHZlcmlsZXJpIHZhcnNhecSxbGFuIG1hxJ9hemF5YSBiYcSfbGEKVVBEQVRFIGBzaXBhcmlzbGVyYCAgICBTRVQgbWFnYXphX2lkID0gMSBXSEVSRSBtYWdhemFfaWQgPSAwIE9SIG1hZ2F6YV9pZCBJUyBOVUxMOwpVUERBVEUgYHVydW5fc2F0aXNgICAgIFNFVCBtYWdhemFfaWQgPSAxIFdIRVJFIG1hZ2F6YV9pZCA9IDAgT1IgbWFnYXphX2lkIElTIE5VTEw7ClVQREFURSBgdHJlbmR5b2xfdXJ1bmxlcmAgU0VUIG1hZ2F6YV9pZCA9IDEgV0hFUkUgbWFnYXphX2lkID0gMCBPUiBtYWdhemFfaWQgSVMgTlVMTDsKVVBEQVRFIGBtYWxpeWV0bGVyYCAgICBTRVQgbWFnYXphX2lkID0gMSBXSEVSRSBtYWdhemFfaWQgPSAwIE9SIG1hZ2F6YV9pZCBJUyBOVUxMOy0tID09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PQotLSAgTcSwR1JBU1lPTjogUEhQIGtvZHUgw7x6ZXJpbmRlbiB5YXDEsWzEsXIgKGluZGV4LnBocCBib290KQotLSAgTXlTUUwnZGUgQUREIENPTFVNTiBJRiBOT1QgRVhJU1RTIGRlc3Rla2xlbm1lZGnEn2kgacOnaW4KLS0gIG1pZ3JhdGlvbiBJTkZPUk1BVElPTl9TQ0hFTUEgc29yZ3VzdSBpbGUgUEhQJ2RlIMOnYWzEscWfxLFyLgotLSA9PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT09PT0KCi0tIFZhcnNhecSxbGFuIG1hxJ9hemEgdmUga3VsbGFuxLFjxLEgKG1pZ3Jhc3lvbiBpw6dpbikKSU5TRVJUIElHTk9SRSBJTlRPIGBrdWxsYW5pY2lsYXJgIChpZCwgZW1haWwsIHNpZnJlLCBhZF9zb3lhZCwgcm9sLCBha3RpZikKICBWQUxVRVMgKDEsICdhZG1pbkBsb2NhbGhvc3QnLCAnJDJ5JDEwJGR1bW15Lmhhc2gucGxhY2Vob2xkZXIubm90LnZhbGlkJywgJ1Npc3RlbSBBZG1pbicsICdhZG1pbicsIDEpOwoKSU5TRVJUIElHTk9SRSBJTlRPIGBtYWdhemFsYXJgIChpZCwga3VsbGFuaWNpX2lkLCBtYWdhemFfYWRpLCBha3RpZikKICBWQUxVRVMgKDEsIDEsICdWYXJzYXlpbGFuIE1hZ2F6YScsIDEpOwoKLS0gLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQotLSAgU2V0dGxlbWVudHMgKENhcmkgSGVzYXAgRWtzdHJlc2kpCi0tIC0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0KQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYHNldHRsZW1lbnRzYCAoCiAgICBgaWRgICAgICAgICAgICAgICAgSU5UIFVOU0lHTkVEIEFVVE9fSU5DUkVNRU5UIFBSSU1BUlkgS0VZLAogICAgYG1hZ2F6YV9pZGAgICAgICAgIElOVCBVTlNJR05FRCBOT1QgTlVMTCBERUZBVUxUIDEsCiAgICBgc2V0dGxlbWVudF9pZGAgICAgVkFSQ0hBUig4MCksCiAgICBgb3JkZXJfbnVtYmVyYCAgICAgVkFSQ0hBUig1MCksCiAgICBgYmFyY29kZWAgICAgICAgICAgVkFSQ0hBUigxMDApLAogICAgYHRyYW5zYWN0aW9uX3R5cGVgIFZBUkNIQVIoNDApLAogICAgYGFtb3VudGAgICAgICAgICAgIERFQ0lNQUwoMTIsMikgREVGQVVMVCAwLAogICAgYGNvbW1pc3Npb25gICAgICAgIERFQ0lNQUwoMTIsMikgREVGQVVMVCAwLAogICAgYGNhcmdvYCAgICAgICAgICAgIERFQ0lNQUwoMTIsMikgREVGQVVMVCAwLAogICAgYHBheW91dGAgICAgICAgICAgIERFQ0lNQUwoMTIsMikgREVGQVVMVCAwLAogICAgYGN1cnJlbmN5YCAgICAgICAgIFZBUkNIQVIoMTApIERFRkFVTFQgJ1RSWScsCiAgICBgc2V0dGxlbWVudF9kYXRlYCAgREFURSwKICAgIGBwYXltZW50X29yZGVyX2lkYCBWQVJDSEFSKDgwKSwKICAgIGByYXdfanNvbmAgICAgICAgICBURVhULAogICAgYGNyZWF0ZWRfYXRgICAgICAgIERBVEVUSU1FIERFRkFVTFQgQ1VSUkVOVF9USU1FU1RBTVAsCiAgICBVTklRVUUgS0VZIGB1a19zZXR0bGVtZW50YCAoYG1hZ2F6YV9pZGAsIGBzZXR0bGVtZW50X2lkYCksCiAgICBJTkRFWCBgaWR4X21hZ2F6YWAgICAgICAgKGBtYWdhemFfaWRgKSwKICAgIElOREVYIGBpZHhfb3JkZXJfbnVtYmVyYCAoYG9yZGVyX251bWJlcmApLAogICAgSU5ERVggYGlkeF90eXBlYCAgICAgICAgIChgdHJhbnNhY3Rpb25fdHlwZWApLAogICAgSU5ERVggYGlkeF9kYXRlYCAgICAgICAgIChgc2V0dGxlbWVudF9kYXRlYCksCiAgICBGT1JFSUdOIEtFWSAoYG1hZ2F6YV9pZGApIFJFRkVSRU5DRVMgYG1hZ2F6YWxhcmAoYGlkYCkgT04gREVMRVRFIENBU0NBREUKKSBFTkdJTkU9SW5ub0RCIERFRkFVTFQgQ0hBUlNFVD11dGY4bWI0OwoKLS0gLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQotLSAgw5ZkZW1lIERldGF5IChPZGVtZURldGF5X1RSXyoueGxzeCkKLS0gLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLQpDUkVBVEUgVEFCTEUgSUYgTk9UIEVYSVNUUyBgb2RlbWVfZGV0YXlgICgKICAgIGBpZGAgICAgICAgICAgICAgICAgICBJTlQgVU5TSUdORUQgQVVUT19JTkNSRU1FTlQgUFJJTUFSWSBLRVksCiAgICBgbWFnYXphX2lkYCAgICAgICAgICAgSU5UIFVOU0lHTkVEIE5PVCBOVUxMIERFRkFVTFQgMSwKICAgIGBrYXlpdF9ub2AgICAgICAgICAgICBWQVJDSEFSKDgwKSwKICAgIGB1bGtlYCAgICAgICAgICAgICAgICBWQVJDSEFSKDUwKSBERUZBVUxUICdUw7xya2l5ZScsCiAgICBgaXNsZW1fdGlwaWAgICAgICAgICAgVkFSQ0hBUig2MCksCiAgICBgc2lwYXJpc19ub2AgICAgICAgICAgVkFSQ0hBUig1MCksCiAgICBgc2lwYXJpc190YXJpaGlgICAgICAgREFURVRJTUUsCiAgICBgaXNsZW1fdGFyaWhpYCAgICAgICAgREFURVRJTUUsCiAgICBgdXJ1bl9hZGlgICAgICAgICAgICAgVEVYVCwKICAgIGBiYXJrb2RgICAgICAgICAgICAgICBWQVJDSEFSKDIwMCksCiAgICBga29taXN5b25fb3JhbmlgICAgICAgREVDSU1BTCg4LDQpIERFRkFVTFQgMCwKICAgIGB0eV9oYWtlZGlzYCAgICAgICAgICBERUNJTUFMKDEyLDIpIERFRkFVTFQgMCwKICAgIGBzYXRpY2lfaGFrZWRpc2AgICAgICBERUNJTUFMKDEyLDIpIERFRkFVTFQgMCwKICAgIGBzdG9wYWpgICAgICAgICAgICAgICBERUNJTUFMKDEyLDIpIERFRkFVTFQgMCwKICAgIGBrZHZfb3JhbmlgICAgICAgICAgICBERUNJTUFMKDYsMikgREVGQVVMVCAwLAogICAgYHZhZGVfc3VyZXNpYCAgICAgICAgIElOVCBERUZBVUxUIDAsCiAgICBgdGVzbGltX3RhcmloaWAgICAgICAgREFURVRJTUUsCiAgICBgdmFkZV90YXJpaGlgICAgICAgICAgREFURVRJTUUsCiAgICBgdG9wbGFtX3R1dGFyYCAgICAgICAgREVDSU1BTCgxMiwyKSBERUZBVUxUIDAsCiAgICBgbXVzdGVyaWAgICAgICAgICAgICAgVkFSQ0hBUigxNTApLAogICAgYHBha2V0X25vYCAgICAgICAgICAgIFZBUkNIQVIoNTApLAogICAgYGRvbmVtX3RhZ2lgICAgICAgICAgIFZBUkNIQVIoMzApLAogICAgYHl1a2xlbWVfdGFyaWhpYCAgICAgIERBVEVUSU1FIERFRkFVTFQgQ1VSUkVOVF9USU1FU1RBTVAsCiAgICBJTkRFWCBgaWR4X21hZ2F6YWAgICAgKGBtYWdhemFfaWRgKSwKICAgIElOREVYIGBpZHhfc2lwbm9gICAgICAoYHNpcGFyaXNfbm9gKSwKICAgIElOREVYIGBpZHhfaXNsZW1gICAgICAoYGlzbGVtX3RpcGlgKSwKICAgIElOREVYIGBpZHhfdmFkZWAgICAgICAoYHZhZGVfdGFyaWhpYCksCiAgICBJTkRFWCBgaWR4X2RvbmVtYCAgICAgKGBkb25lbV90YWdpYCksCiAgICBGT1JFSUdOIEtFWSAoYG1hZ2F6YV9pZGApIFJFRkVSRU5DRVMgYG1hZ2F6YWxhcmAoYGlkYCkgT04gREVMRVRFIENBU0NBREUKKSBFTkdJTkU9SW5ub0RCIERFRkFVTFQgQ0hBUlNFVD11dGY4bWI0Owo=');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt) try { DB::get()->exec($stmt); } catch(PDOException $e) {}
    }

    // 2. Migration: mevcut tablolara eksik kolonları ekle (MySQL uyumlu)
    $migrate = function(string $table, string $column, string $definition) {
        try {
            $exists = DB::scalar(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$table, $column]
            );
            if (!$exists) {
                DB::get()->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            }
        } catch (PDOException $e) { /* sessizce geç */ }
    };

    $migrate('siparisler',       'magaza_id',            'INT UNSIGNED NOT NULL DEFAULT 1');
    $migrate('siparisler',       'musteri_tam_ad',        'VARCHAR(100)');
    $migrate('siparisler',       'kargo_takip_no',        'VARCHAR(50)');
    $migrate('siparisler',       'kargo_firmasi',         'VARCHAR(60)');
    $migrate('siparisler',       'api_statusu',           'VARCHAR(40)');
    $migrate('siparisler',       'shipment_package_id',   'VARCHAR(50)');
    $migrate('siparisler',       'api_son_guncelleme',    'DATETIME');
    $migrate('urun_satis',       'magaza_id',             'INT UNSIGNED NOT NULL DEFAULT 1');
    $migrate('trendyol_urunler', 'magaza_id',             'INT UNSIGNED NOT NULL DEFAULT 1');
    $migrate('maliyetler',       'magaza_id',             'INT UNSIGNED NOT NULL DEFAULT 1');
    $migrate('siparis_satirlari','magaza_id',             'INT UNSIGNED NOT NULL DEFAULT 1');

    // anthropic_api_key kolonu
    $migrate('magazalar','anthropic_api_key', 'VARCHAR(200) DEFAULT NULL');
    $migrate('komisyon_tarifeleri','guncel_tsf', 'DECIMAL(10,2) DEFAULT 0');

    // AI yorumları cache tablosu
    try {
        DB::get()->exec("CREATE TABLE IF NOT EXISTS `ai_yorumlar` (
            `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `magaza_id`    INT UNSIGNED NOT NULL,
            `tip`          VARCHAR(30) NOT NULL,
            `donem`        VARCHAR(20) NOT NULL,
            `yorum`        MEDIUMTEXT,
            `olusturuldu`  DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_ai` (`magaza_id`,`tip`,`donem`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch(PDOException $e) {}

    // Ödeme Detay tablosu
    try {
        DB::get()->exec("CREATE TABLE IF NOT EXISTS `odeme_detay` (
            `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `magaza_id`      INT UNSIGNED NOT NULL DEFAULT 1,
            `kayit_no`       VARCHAR(80),
            `ulke`           VARCHAR(50) DEFAULT 'Türkiye',
            `islem_tipi`     VARCHAR(60),
            `siparis_no`     VARCHAR(50),
            `siparis_tarihi` DATETIME,
            `islem_tarihi`   DATETIME,
            `urun_adi`       TEXT,
            `barkod`         VARCHAR(200),
            `komisyon_orani` DECIMAL(8,4) DEFAULT 0,
            `ty_hakedis`     DECIMAL(12,2) DEFAULT 0,
            `satici_hakedis` DECIMAL(12,2) DEFAULT 0,
            `stopaj`         DECIMAL(12,2) DEFAULT 0,
            `kdv_orani`      DECIMAL(6,2) DEFAULT 0,
            `vade_suresi`    INT DEFAULT 0,
            `teslim_tarihi`  DATETIME,
            `vade_tarihi`    DATETIME,
            `toplam_tutar`   DECIMAL(12,2) DEFAULT 0,
            `musteri`        VARCHAR(150),
            `paket_no`       VARCHAR(50),
            `donem_tagi`     VARCHAR(30),
            `yukleme_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_magaza`  (`magaza_id`),
            INDEX `idx_sipno`   (`siparis_no`),
            INDEX `idx_islem`   (`islem_tipi`),
            INDEX `idx_vade`    (`vade_tarihi`),
            INDEX `idx_donem`   (`donem_tagi`),
            FOREIGN KEY (`magaza_id`) REFERENCES `magazalar`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch(PDOException $e) {}

    // Migration: odeme_detay'daki boş donem_tagi'leri işlem tarihinden doldur
    try {
        DB::get()->exec("
            UPDATE odeme_detay
            SET donem_tagi = DATE_FORMAT(islem_tarihi, '%Y-%m-01')
            WHERE (donem_tagi = '' OR donem_tagi IS NULL)
              AND islem_tarihi IS NOT NULL
        ");
    } catch(PDOException $e) {}

    // 3. Mevcut verileri varsayılan mağazaya (id=1) bağla
    // komisyon_tarifeleri tablosu
    try {
        DB::get()->exec("CREATE TABLE IF NOT EXISTS `komisyon_tarifeleri` (
            `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `magaza_id`     INT UNSIGNED NOT NULL DEFAULT 1,
            `urun_adi`      VARCHAR(500),
            `barcode`       VARCHAR(200),
            `stok_kodu`     VARCHAR(200),
            `beden`         VARCHAR(100),
            `model_kodu`    VARCHAR(200),
            `kategori`      VARCHAR(200),
            `marka`         VARCHAR(200),
            `stok`          INT DEFAULT 0,
            `fiyat_limit_1` DECIMAL(10,2) DEFAULT 0,
            `fiyat_limit_2` DECIMAL(10,2) DEFAULT 0,
            `fiyat_limit_3` DECIMAL(10,2) DEFAULT 0,
            `komisyon_1`    DECIMAL(6,2) DEFAULT 0,
            `komisyon_2`    DECIMAL(6,2) DEFAULT 0,
            `komisyon_3`    DECIMAL(6,2) DEFAULT 0,
            `komisyon_4`    DECIMAL(6,2) DEFAULT 0,
            `guncel_fiyat`  DECIMAL(10,2) DEFAULT 0,
            `guncel_komisyon` DECIMAL(6,2) DEFAULT 0,
            `yukleme_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_magaza` (`magaza_id`),
            INDEX `idx_barcode` (`barcode`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch(PDOException $e) {}

    // talepler tablosu — Faz 2
    try {
        DB::get()->exec("CREATE TABLE IF NOT EXISTS `talepler` (
            `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `magaza_id`       INT UNSIGNED NOT NULL DEFAULT 1,
            `claim_id`        VARCHAR(80),
            `siparis_no`      VARCHAR(50),
            `line_item_id`    VARCHAR(80),
            `barcode`         VARCHAR(100),
            `urun_adi`        VARCHAR(300),
            `talep_tipi`      VARCHAR(60),
            `talep_statusu`   VARCHAR(60),
            `talep_tarihi`    DATETIME,
            `iade_tutari`     DECIMAL(12,2) DEFAULT 0,
            `musteri`         VARCHAR(150),
            `kargo_takip_no`  VARCHAR(80),
            `neden`           TEXT,
            `ty_urun_id`      VARCHAR(60) NULL,
            `raw_json`        TEXT,
            `yukleme_tarihi`  DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_claim` (`magaza_id`, `claim_id`),
            INDEX `idx_magaza`  (`magaza_id`),
            INDEX `idx_sipno`   (`siparis_no`),
            INDEX `idx_barcode` (`barcode`),
            INDEX `idx_tip`     (`talep_tipi`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch(PDOException $e) {}

    // musteri_sorulari tablosu — Faz 2
    try {
        DB::get()->exec("CREATE TABLE IF NOT EXISTS `musteri_sorulari` (
            `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `magaza_id`      INT UNSIGNED NOT NULL DEFAULT 1,
            `question_id`    VARCHAR(80),
            `barcode`        VARCHAR(100),
            `urun_adi`       VARCHAR(300),
            `soru_metni`     TEXT,
            `cevap_metni`    TEXT,
            `soru_tarihi`    DATETIME,
            `cevap_tarihi`   DATETIME,
            `cevap_durumu`   VARCHAR(30) DEFAULT 'Cevaplanmadı',
            `ty_urun_id`     VARCHAR(60) NULL,
            `yukleme_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_question` (`magaza_id`, `question_id`),
            INDEX `idx_magaza`  (`magaza_id`),
            INDEX `idx_barcode` (`barcode`),
            INDEX `idx_durum`   (`cevap_durumu`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch(PDOException $e) {}

    // reklamlar tablosu
    try {
        DB::get()->exec("CREATE TABLE IF NOT EXISTS `reklamlar` (
            `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `magaza_id`       INT UNSIGNED NOT NULL DEFAULT 1,
            `reklam_adi`      VARCHAR(200),
            `statu`           VARCHAR(50),
            `baslangic_tarihi` VARCHAR(30),
            `bitis_tarihi`    VARCHAR(30),
            `urun_adedi`      INT DEFAULT 0,
            `toplam_butce`    DECIMAL(12,2) DEFAULT 0,
            `kalan_butce`     DECIMAL(12,2) DEFAULT 0,
            `harcama`         DECIMAL(12,2) DEFAULT 0,
            `tbm_teklif`      VARCHAR(50),
            `gerceklesen_tbm` DECIMAL(8,2) DEFAULT 0,
            `tiklanma`        INT DEFAULT 0,
            `goruntulenme`    INT DEFAULT 0,
            `dogrudan_satis`  INT DEFAULT 0,
            `dolayli_satis`   INT DEFAULT 0,
            `toplam_satis`    INT DEFAULT 0,
            `dogrudan_ciro`   DECIMAL(12,2) DEFAULT 0,
            `dolayli_ciro`    DECIMAL(12,2) DEFAULT 0,
            `toplam_ciro`     DECIMAL(12,2) DEFAULT 0,
            `roas`            DECIMAL(8,2) DEFAULT 0,
            `yukleme_tarihi`  DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_magaza` (`magaza_id`),
            INDEX `idx_tarih`  (`baslangic_tarihi`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch(PDOException $e) {}

    foreach (['siparisler','urun_satis','trendyol_urunler','maliyetler'] as $tbl) {
        try { DB::get()->exec("UPDATE `$tbl` SET magaza_id=1 WHERE magaza_id=0"); } catch(PDOException $e) {}
    }

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

// Auth kontrolü — DB hatası varsa login'e yönlendirme, hata sayfası göster
if (!$dbError) {
    requireLogin();
    if (!authMagaza()) { header('Location: magaza_sec.php'); exit; }
}

// Autoload ve diğer require'lar
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/TrendyolApi.php';

$authUser   = authUser();
$magaza     = authMagaza();
$magazaId   = (int)$magaza['id'];
$action     = $_GET['action'] ?? 'dashboard';
$ajaxUrl    = rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/ajax.php';
$message    = ''; $error = '';

// ---- Excel yükleme ----
// Komisyon Tarifeleri Excel — ana handler taşımadan önce çalışır
if (!$dbError && $_SERVER['REQUEST_METHOD']==='POST'
    && ($_POST['file_type']??'')==='komisyon_tarifeleri'
    && isset($_FILES['excel_file'])
    && $_FILES['excel_file']['error']===0) {
    try {
        $kRows = IOFactory::load($_FILES['excel_file']['tmp_name'])
                    ->getActiveSheet()->toArray(null, true, true, false);
        $kNow = date('Y-m-d H:i:s');
        $kpN = function($v) {
            $s = trim((string)$v);
            if ($s==='' || $s==='-' || $s===null) return 0.0;
            $s = preg_replace('/[^0-9,.]/', '', $s);
            if (strpos($s,'.')!==false && strpos($s,',')!==false) {
                $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s);
            } elseif (strpos($s,',')!==false) { $s = str_replace(',', '.', $s); }
            elseif (strpos($s,'.')!==false) {
                $parts = explode('.', $s); $last = end($parts);
                if (strlen($last)===3) $s = str_replace('.', '', $s);
            }
            return (float)$s;
        };
        DB::exec("DELETE FROM komisyon_tarifeleri WHERE magaza_id=?", [$magazaId]);
        $kIns = 0;
        foreach ($kRows as $ki => $r) {
            if ($ki===0 || empty($r[0])) continue;
            DB::exec(
                "INSERT INTO komisyon_tarifeleri
                 (magaza_id,urun_adi,barcode,stok_kodu,beden,model_kodu,kategori,marka,stok,
                  fiyat_limit_1,fiyat_limit_2,fiyat_limit_3,
                  komisyon_1,komisyon_2,komisyon_3,komisyon_4,
                  guncel_fiyat,guncel_komisyon,guncel_tsf,yukleme_tarihi)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$magazaId,(string)($r[0]??''),(string)($r[1]??''),(string)($r[2]??''),
                 (string)($r[3]??''),(string)($r[4]??''),(string)($r[5]??''),(string)($r[6]??''),
                 (int)($r[7]??0),
                 $kpN($r[8]??0),$kpN($r[10]??0),$kpN($r[12]??0),
                 $kpN($r[15]??0),$kpN($r[16]??0),$kpN($r[17]??0),$kpN($r[18]??0),
                 $kpN($r[19]??0),$kpN($r[20]??0),$kpN($r[21]??0),$kNow]
            );
            $kIns++;
        }
        $message = "✅ $kIns komisyon tarifesi yüklendi";
    } catch (Exception $e) { $error = "❌ Komisyon hatası: ".$e->getMessage(); }
}

// Reklam Excel — tmp_name kullanmadan ÖNCE ana handler taşır
if (!$dbError && $_SERVER['REQUEST_METHOD']==='POST'
    && ($_POST['file_type']??'')==='reklamlar'
    && isset($_FILES['excel_file'])
    && $_FILES['excel_file']['error']===0) {
    try {
        $rRows = IOFactory::load($_FILES['excel_file']['tmp_name'])
                    ->getActiveSheet()->toArray(null, true, true, false);
        $rNow = date('Y-m-d H:i:s');
        $pN = function($v) {
            $s = trim((string)$v);
            if ($s==='' || $s==='-') return 0.0;
            $s = preg_replace('/[^0-9,.]/', '', $s);
            if ($s==='') return 0.0;
            if (strpos($s,'.')!==false && strpos($s,',')!==false) {
                // 1.349,50 → nokta binlik, virgül ondalık
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } elseif (strpos($s,',')!==false) {
                // 249,67 → virgül ondalık
                $s = str_replace(',', '.', $s);
            } elseif (strpos($s,'.')!==false) {
                // Sadece nokta: son noktadan sonra tam 3 hane → binlik ayırıcı (5.670)
                $parts = explode('.', $s);
                $last  = end($parts);
                if (strlen($last)===3) {
                    $s = str_replace('.', '', $s); // 5.670 → 5670
                }
                // değilse ondalık nokta bırak: 5.67 → 5.67
            }
            return (float)$s;
        };
        DB::exec("DELETE FROM reklamlar WHERE magaza_id=?", [$magazaId]);
        $rIns = 0;
        foreach ($rRows as $ri => $r) {
            if ($ri===0 || empty($r[0])) continue;
            DB::exec(
                "INSERT INTO reklamlar
                 (magaza_id,reklam_adi,statu,baslangic_tarihi,bitis_tarihi,urun_adedi,
                  toplam_butce,kalan_butce,harcama,gerceklesen_tbm,tiklanma,goruntulenme,
                  dogrudan_satis,dolayli_satis,toplam_satis,
                  dogrudan_ciro,dolayli_ciro,toplam_ciro,roas,yukleme_tarihi)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$magazaId,(string)($r[0]??''),(string)($r[1]??''),(string)($r[2]??''),
                 (string)($r[3]??''),(int)($r[4]??0),$pN($r[6]??0),$pN($r[8]??0),
                 $pN($r[9]??0),$pN($r[11]??0),(int)($r[12]??0),(int)($r[13]??0),
                 (int)($r[14]??0),(int)($r[15]??0),(int)($r[16]??0),
                 $pN($r[17]??0),$pN($r[18]??0),$pN($r[19]??0),$pN($r[20]??0),$rNow]
            );
            $rIns++;
        }
        $message = "✅ $rIns reklam yüklendi";
    } catch (Exception $e) { $error = "❌ Reklam hatası: ".$e->getMessage(); }
}

// Ödeme Detay Excel yükleme
if (!$dbError && $_SERVER['REQUEST_METHOD']==='POST'
    && ($_POST['file_type']??'')==='odeme_detay'
    && isset($_FILES['excel_file'])
    && $_FILES['excel_file']['error']===0) {
    try {
        $odRows = IOFactory::load($_FILES['excel_file']['tmp_name'])
                    ->getActiveSheet()->toArray(null, true, true, false);
        $odNow = date('Y-m-d H:i:s');

        // Tarih parse yardımcısı: "03.04.2026 11:30" → "2026-04-03 11:30:00"
        $odDate = function($v) {
            if (!$v) return null;
            $s = trim((string)$v);
            if (!$s || $s === '0' || $s === '') return null;
            // Excel numeric date
            if (is_numeric($s)) {
                try {
                    $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$s);
                    return $dt->format('Y-m-d H:i:s');
                } catch(\Exception $e) { return null; }
            }
            // dd.mm.yyyy HH:MM
            if (preg_match('/(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2})/', $s, $m)) {
                return "{$m[3]}-{$m[2]}-{$m[1]} {$m[4]}:{$m[5]}:00";
            }
            // dd.mm.yyyy
            if (preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', $s, $m)) {
                return "{$m[3]}-{$m[2]}-{$m[1]} 00:00:00";
            }
            return null;
        };

        $odNum = function($v) {
            $s = trim((string)$v);
            if ($s==='' || $s==='-') return 0.0;
            $s = preg_replace('/[^0-9,.\-]/', '', $s);
            if (strpos($s,'.')!==false && strpos($s,',')!==false) {
                $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s);
            } elseif (strpos($s,',')!==false) {
                $s = str_replace(',', '.', $s);
            }
            return (float)$s;
        };

        // Dönem tagi: önce dosya adından çıkar (OdemeDetay_TR_2026-04-30_...)
        $fname    = $_FILES['excel_file']['name'] ?? '';
        $donemTag = '';
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $fname, $dm)) $donemTag = $dm[1];
        // Dosya adında tarih yoksa ilk satırın işlem tarihinden türet
        if (!$donemTag && !empty($odRows)) {
            foreach ($odRows as $ri => $rr) {
                if ($ri === 0) continue;
                $islemTarStr = trim((string)($rr[5] ?? ''));
                if (preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', $islemTarStr, $dtm)) {
                    $donemTag = "{$dtm[3]}-{$dtm[2]}-01"; // ayın 1'i
                    break;
                }
            }
        }

        // Önce bu dönemin verilerini sil (aynı dönem tekrar yüklenirse temizle)
        if ($donemTag) {
            DB::exec("DELETE FROM odeme_detay WHERE magaza_id=? AND donem_tagi=?", [$magazaId, $donemTag]);
        }

        $odIns = 0;
        foreach ($odRows as $oi => $r) {
            if ($oi === 0 || (empty($r[0]) && empty($r[3]))) continue;
            // Kolon sırası: 0=KayıtNo, 1=Ülke, 2=İşlemTipi, 3=SiparişNo,
            //               4=SiparişTarihi, 5=İşlemTarihi, 6=Satıcı, 7=SatıcıCariAdı,
            //               8=ÜrünAdı, 9=Barkod, 10=Komisyon%, 11=TYHakediş,
            //               12=SatıcıHakediş, 13=Stopaj, 14=KDV%, 15=VadeSüresi,
            //               16=TeslimTarihi, 17=VadeTarihi, 18=ToplamTutar,
            //               19=MüşteriAdı, 20=PaketNo
            DB::exec(
                "INSERT INTO odeme_detay
                 (magaza_id,kayit_no,ulke,islem_tipi,siparis_no,siparis_tarihi,islem_tarihi,
                  urun_adi,barkod,komisyon_orani,ty_hakedis,satici_hakedis,stopaj,kdv_orani,
                  vade_suresi,teslim_tarihi,vade_tarihi,toplam_tutar,musteri,paket_no,
                  donem_tagi,yukleme_tarihi)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $magazaId,
                    (string)($r[0]??''),
                    (string)($r[1]??'Türkiye'),
                    (string)($r[2]??''),
                    // Sipariş no: float olarak gelebilir (11106594722.0)
                    $r[3] ? (string)(int)(float)$r[3] : '',
                    $odDate($r[4]??null),
                    $odDate($r[5]??null),
                    (string)($r[8]??''),
                    (string)($r[9]??''),
                    $odNum($r[10]??0),
                    $odNum($r[11]??0),
                    $odNum($r[12]??0),
                    $odNum($r[13]??0),
                    $odNum($r[14]??0),
                    (int)($r[15]??0),
                    $odDate($r[16]??null),
                    $odDate($r[17]??null),
                    $odNum($r[18]??0),
                    (string)($r[19]??''),
                    (string)($r[20]??''),
                    $donemTag,
                    $odNow,
                ]
            );
            $odIns++;
        }
        $message = "✅ $odIns ödeme kalemi yüklendi" . ($donemTag ? " ($donemTag dönemi)" : "");
    } catch (Exception $e) { $error = "❌ Ödeme Detay hatası: " . $e->getMessage(); }
}

if (!$dbError && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $type = $_POST['file_type'] ?? '';
    $file = $_FILES['excel_file'];
    if ($file['error'] === 0 && strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) === 'xlsx') {
        $path = __DIR__ . '/uploads/' . uniqid() . '.xlsx';
        move_uploaded_file($file['tmp_name'], $path);
        try {
            $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
            $now  = date('Y-m-d H:i:s');
            if ($type === 'siparisler') {
                $ins = $upd = 0;
                foreach ($rows as $i => $r) {
                    if ($i === 0 || empty($r[1])) continue;
                    try {
                        // INSERT + finansal alanları her durumda güncelle
                        $affected = DB::exec("INSERT INTO siparisler
                            (magaza_id,siparis_tarihi,siparis_no,ulke,siparis_statusu,sirket,odeme_yontemi,musteri,
                             urun_adedi,siparis_tutari,komisyon,indirim,gonderi_kargo,iade_kargo,ceza,
                             iptal,iade,diger,net_tutar,platform_hizmet,yukleme_tarihi)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                            ON DUPLICATE KEY UPDATE
                                siparis_statusu  = VALUES(siparis_statusu),
                                siparis_tutari   = VALUES(siparis_tutari),
                                komisyon         = VALUES(komisyon),
                                indirim          = VALUES(indirim),
                                gonderi_kargo    = VALUES(gonderi_kargo),
                                iade_kargo       = VALUES(iade_kargo),
                                ceza             = VALUES(ceza),
                                iptal            = VALUES(iptal),
                                iade             = VALUES(iade),
                                diger            = VALUES(diger),
                                net_tutar        = VALUES(net_tutar),
                                platform_hizmet  = VALUES(platform_hizmet),
                                urun_adedi       = VALUES(urun_adedi),
                                yukleme_tarihi   = VALUES(yukleme_tarihi)",
                            [$magazaId,$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],
                             (float)($r[7]??0),(float)($r[8]??0),(float)($r[9]??0),
                             (float)($r[10]??0),(float)($r[11]??0),(float)($r[12]??0),
                             (float)($r[13]??0),(float)($r[14]??0),(float)($r[15]??0),
                             (float)($r[16]??0),(float)($r[17]??0),(float)($r[20]??0),$now]);
                        // MySQL: INSERT=1, UPDATE=2, no change=0
                        if ($affected === 1) $ins++;
                        elseif ($affected === 2) $upd++;
                    } catch(PDOException $e) { /* skip */ }
                }
                if (DB::scalar("SELECT COUNT(*) FROM trendyol_urunler WHERE magaza_id=?",[$magazaId]) > 0) {
                    (new TrendyolApi($magaza))->rematch($magazaId);
                }
                // Mevcut İngilizce statüleri Türkçeye normalize et
                $statusMap = [
                    'Delivered'=>'Teslim Edildi','Cancelled'=>'İptal Edildi',
                    'UnDelivered'=>'Teslim Edilemedi','Returned'=>'İade Edildi',
                    'Created'=>'Yeni Sipariş','Picking'=>'Hazırlanıyor',
                    'Invoiced'=>'Faturalandı','Shipped'=>'Kargoya Verildi',
                    'Awaiting'=>'Beklemede','InTransit'=>'Yolda',
                    'OutForDelivery'=>'Dağıtımda',
                ];
                foreach ($statusMap as $en => $tr) {
                    DB::exec("UPDATE siparisler SET siparis_statusu=? WHERE magaza_id=? AND siparis_statusu=?",
                        [$tr, $magazaId, $en]);
                }
                $message = "✅ $ins yeni sipariş eklendi, $upd sipariş güncellendi (komisyon/kargo/platform)";
            } elseif ($type === 'satis_raporu') {
                DB::exec("DELETE FROM urun_satis WHERE magaza_id=?", [$magazaId]);
                $ins = 0;
                foreach ($rows as $i => $r) {
                    if ($i === 0 || empty($r[0])) continue;
                    DB::exec("INSERT INTO urun_satis
                        (magaza_id,barkod,urun_adi,model_kodu,kategori,marka,renk,beden,
                         brut_siparis,brut_satis,iptal_adedi,iptal_orani,iade_adedi,iade_orani,
                         net_satis,brut_ciro,indirim_tutari,net_ciro,toplam_komisyon,
                         ort_komisyon,ort_komisyon_orani,ort_satis_fiyati,guncel_fiyat,guncel_stok,yukleme_tarihi)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                        [$magazaId,$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],
                         (float)($r[7]??0),(float)($r[8]??0),(float)($r[9]??0),(float)($r[10]??0),
                         (float)($r[11]??0),(float)($r[12]??0),(float)($r[13]??0),(float)($r[14]??0),
                         (float)($r[15]??0),(float)($r[16]??0),(float)($r[17]??0),(float)($r[18]??0),
                         (float)($r[19]??0),(float)($r[20]??0),(float)($r[21]??0),(float)($r[22]??0),$now]);
                    $ins++;
                }
                if (DB::scalar("SELECT COUNT(*) FROM trendyol_urunler WHERE magaza_id=?",[$magazaId]) > 0) {
                    (new TrendyolApi($magaza))->rematch($magazaId);
                }
                $message = "✅ $ins ürün satış verisi yüklendi";
            }
        } catch (Exception $e) { $error = "❌ " . $e->getMessage(); }
        unlink($path);
    } else { $error = "❌ Sadece .xlsx dosyaları kabul edilir."; }
}


// ---- İstatistikler ----
$stats = [];
if (!$dbError) {
    $stats['toplam_siparis']       = (int)DB::scalar("SELECT COUNT(*) FROM siparisler WHERE magaza_id=?",[$magazaId]) ?: 0;
    $stats['toplam_siparis_tutari']= (float)DB::scalar("SELECT COALESCE(SUM(siparis_tutari),0) FROM siparisler WHERE magaza_id=?",[$magazaId]) ?: 0;
    $stats['toplam_net_tutar']     = (float)DB::scalar("SELECT COALESCE(SUM(net_tutar),0) FROM siparisler WHERE magaza_id=?",[$magazaId]) ?: 0;
    $stats['toplam_komisyon']      = (float)DB::scalar("SELECT COALESCE(SUM(ABS(komisyon)),0) FROM siparisler WHERE magaza_id=?",[$magazaId]) ?: 0;
    $stats['toplam_kargo']         = (float)DB::scalar("SELECT COALESCE(SUM(ABS(gonderi_kargo)+ABS(iade_kargo)),0) FROM siparisler WHERE magaza_id=?",[$magazaId]) ?: 0;
    $stats['toplam_platform']      = (float)DB::scalar("SELECT COALESCE(SUM(ABS(platform_hizmet)),0) FROM siparisler WHERE magaza_id=?",[$magazaId]) ?: 0;
    $stats['urun_satis_sayisi']    = (int)DB::scalar("SELECT COUNT(*) FROM urun_satis WHERE magaza_id=?",[$magazaId]) ?: 0;
    $stats['ty_urun_sayisi']       = (int)DB::scalar("SELECT COUNT(*) FROM trendyol_urunler WHERE magaza_id=?",[$magazaId]) ?: 0;
    $stats['maliyet_sayisi']       = (int)DB::scalar("SELECT COUNT(*) FROM maliyetler WHERE magaza_id=?",[$magazaId]) ?: 0;
    $stats['eslesme_siparis']      = (int)DB::scalar("SELECT COUNT(*) FROM siparisler WHERE magaza_id=? AND ty_urun_id IS NOT NULL AND ty_urun_id!=''", [$magazaId]) ?: 0;
    $stats['api_siparis_satirlari'] = (int)DB::scalar("SELECT COUNT(DISTINCT siparis_no) FROM siparis_satirlari WHERE magaza_id=?",[$magazaId]) ?: 0;
    $lastOrderSync = DB::scalar("SELECT MAX(api_son_guncelleme) FROM siparisler WHERE magaza_id=? AND api_son_guncelleme IS NOT NULL", [$magazaId]);

    $stats['eslesme_urun']         = (int)DB::scalar("SELECT COUNT(*) FROM urun_satis WHERE magaza_id=? AND ty_urun_id IS NOT NULL AND ty_urun_id!=''", [$magazaId]) ?: 0;

    $toplamUrunMaliyeti = (float)DB::scalar("
        SELECT COALESCE(SUM((m.birim_maliyet+m.kargo_maliyeti+m.paket_maliyeti+m.diger_maliyet)*u.net_satis),0)
        FROM urun_satis u JOIN maliyetler m ON u.ty_urun_id=m.ty_urun_id AND m.magaza_id=u.magaza_id
        WHERE u.magaza_id=?
    ", [$magazaId]) ?: 0;
    $stats['toplam_urun_maliyeti'] = $toplamUrunMaliyeti;
    // toplam_net_tutar = siparisler.net_tutar toplamı (komisyon+kargo+platform ZATEN düşülmüş)
    // Bu yüzden sadece ürün maliyeti çıkarılır, kargo/platform tekrar çıkarılmaz
    $stats['kar']       = $stats['toplam_net_tutar'] - $toplamUrunMaliyeti;
    $stats['kar_marji'] = $stats['toplam_net_tutar'] > 0 ? round($stats['kar']/$stats['toplam_net_tutar']*100, 1) : 0;

    // Statüları ham çek, PHP'de Türkçeye çevir ve grupla
    $statusMapTR = [
        'UnDeliveredAndReturned' => 'İade Edildi',
        'UnPacked'               => 'Hazırlanıyor',
        'AtCollectionPoint'      => 'Teslim Noktasında',
        'Shipped'                => 'Kargoya Verildi',
        'Delivered'              => 'Teslim Edildi',
        'Cancelled'              => 'İptal Edildi',
        'Created'                => 'Yeni Sipariş',
        'Picking'                => 'Hazırlanıyor',
        'Invoiced'               => 'Faturalandi',
        'Returned'               => 'İade Edildi',
        'ReadyToShip'            => 'Kargoya Hazır',
        'InTransit'              => 'Kargoda',
        'WaitingForSupply'       => 'Tedarik Bekleniyor',
        'Suspended'              => 'Askıya Alındı',
    ];
    // Her iki kolonu da ayrı ayrı çek, PHP'de birleştir
    $_rawStatus = DB::rows("
        SELECT
            CASE WHEN api_statusu IS NOT NULL AND api_statusu != '' THEN api_statusu
                 ELSE siparis_statusu END AS raw_s,
            COUNT(*) as adet
        FROM siparisler
        WHERE magaza_id=?
        GROUP BY raw_s
    ", [$magazaId]);
    $_grouped = [];
    foreach ($_rawStatus as $_r) {
        $_tr = $statusMapTR[$_r['raw_s']] ?? $_r['raw_s'];
        $_grouped[$_tr] = ($_grouped[$_tr] ?? 0) + (int)$_r['adet'];
    }
    arsort($_grouped);
    $statusData = [];
    foreach ($_grouped as $_s => $_n) $statusData[] = ['siparis_statusu' => $_s, 'adet' => $_n];
    $gunluk     = array_reverse(DB::rows("SELECT LEFT(siparis_tarihi,10) as gun, SUM(siparis_tutari) as ciro, SUM(net_tutar) as net, COUNT(*) as adet FROM siparisler WHERE magaza_id=? AND siparis_tarihi!='' GROUP BY gun ORDER BY gun DESC LIMIT 30", [$magazaId]));
    $kategoriData = DB::rows("SELECT kategori, SUM(net_satis) as net_adet, SUM(net_ciro) as ciro FROM urun_satis WHERE magaza_id=? GROUP BY kategori ORDER BY ciro DESC LIMIT 8", [$magazaId]);
} else {
    $statusData = $gunluk = $kategoriData = [];
}

$api    = new TrendyolApi($magaza);
$apiOk  = $api->isConfigured();

function fmt($n, $d=2) { return number_format((float)$n, $d, ',', '.'); }
function fmtTL($n)     { return fmt($n) . ' ₺'; }
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Trendyol Kar/Zarar Analizi</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
:root{--primary:#f27a1a;--primary-dark:#d4610a;--bg:#0f1117;--bg2:#1a1d2e;--bg3:#252840;--card:#1e2035;--border:#2e3150;--text:#e8eaf6;--text2:#9099c4;--green:#2ecc71;--red:#e74c3c;--yellow:#f1c40f;--blue:#3498db;}
*{margin:0;padding:0;box-sizing:border-box;}
body{background:var(--bg);color:var(--text);font-family:'Segoe UI',sans-serif;font-size:14px;}
.sidebar{position:fixed;left:0;top:0;width:220px;height:100vh;background:var(--bg2);border-right:1px solid var(--border);padding:20px 0;z-index:100;overflow-y:auto;}
.sidebar .logo{padding:10px 20px 25px;font-size:16px;font-weight:700;color:var(--primary);border-bottom:1px solid var(--border);margin-bottom:15px;}
.sidebar .logo span{display:block;font-size:11px;color:var(--text2);font-weight:400;margin-top:2px;}
.sidebar a{display:flex;align-items:center;gap:10px;padding:10px 20px;color:var(--text2);text-decoration:none;transition:.2s;font-size:13px;}
.sidebar a:hover,.sidebar a.active{background:rgba(242,122,26,.15);color:var(--primary);border-right:3px solid var(--primary);}
.sidebar .sep{padding:8px 20px 4px;font-size:10px;text-transform:uppercase;color:var(--border);letter-spacing:1px;}
.main{margin-left:220px;padding:25px;min-height:100vh;}
.page-title{font-size:22px;font-weight:700;margin-bottom:20px;}.page-title span{color:var(--primary);}
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:15px;margin-bottom:25px;}
.kpi{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:18px;position:relative;overflow:hidden;}
.kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--primary);}
.kpi.green::before{background:var(--green);}.kpi.red::before{background:var(--red);}.kpi.blue::before{background:var(--blue);}.kpi.yellow::before{background:var(--yellow);}
.kpi-label{font-size:11px;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;}
.kpi-value{font-size:22px;font-weight:700;}.kpi-sub{font-size:11px;color:var(--text2);margin-top:4px;}
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px;}
.card-title{font-size:15px;font-weight:600;margin-bottom:15px;display:flex;align-items:center;gap:8px;}
table{width:100%;border-collapse:collapse;}
th{text-align:left;padding:10px 12px;font-size:11px;text-transform:uppercase;color:var(--text2);border-bottom:1px solid var(--border);background:var(--bg3);white-space:nowrap;}
td{padding:9px 12px;border-bottom:1px solid var(--border);font-size:13px;vertical-align:middle;}
tr:hover td{background:rgba(255,255,255,.03);}
.positive{color:var(--green);font-weight:600;}.negative{color:var(--red);font-weight:600;}.neutral{color:var(--text2);}
.badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;}
.badge-green{background:rgba(46,204,113,.15);color:var(--green);}.badge-red{background:rgba(231,76,60,.15);color:var(--red);}
.badge-blue{background:rgba(52,152,219,.15);color:var(--blue);}.badge-yellow{background:rgba(241,196,15,.15);color:var(--yellow);}
.badge-orange{background:rgba(242,122,26,.15);color:var(--primary);}.badge-gray{background:rgba(144,153,196,.12);color:var(--text2);}
.form-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:15px;}
.form-group{display:flex;flex-direction:column;gap:6px;}
.form-group label{font-size:12px;color:var(--text2);font-weight:500;}
.form-group input,.form-group select{background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:9px 12px;border-radius:8px;font-size:13px;outline:none;transition:.2s;}
.form-group input:focus,.form-group select:focus{border-color:var(--primary);}
.btn{padding:9px 18px;border-radius:8px;border:none;cursor:pointer;font-size:13px;font-weight:600;transition:.2s;text-decoration:none;display:inline-block;}
.btn-primary{background:var(--primary);color:#fff;}.btn-primary:hover{background:var(--primary-dark);}
.btn-success{background:rgba(46,204,113,.2);color:var(--green);border:1px solid rgba(46,204,113,.3);}
.btn-success:hover{background:rgba(46,204,113,.35);}
.btn-danger{background:rgba(231,76,60,.2);color:var(--red);border:1px solid rgba(231,76,60,.3);}
.btn-danger:hover{background:rgba(231,76,60,.35);}
.btn-sm{padding:4px 10px;font-size:11px;}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:13px;}
.alert-success{background:rgba(46,204,113,.15);border:1px solid rgba(46,204,113,.3);color:var(--green);}
.alert-danger{background:rgba(231,76,60,.15);border:1px solid rgba(231,76,60,.3);color:var(--red);}
.alert-warning{background:rgba(241,196,15,.15);border:1px solid rgba(241,196,15,.3);color:var(--yellow);}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
@media(max-width:900px){.grid-2{grid-template-columns:1fr;}}
.chart-wrap{position:relative;height:260px;}
.progress-bar{background:var(--bg3);border-radius:20px;height:8px;overflow:hidden;margin-top:5px;}
.progress-fill{height:100%;border-radius:20px;background:var(--primary);}
.progress-fill.green{background:var(--green);}.progress-fill.red{background:var(--red);}
.tab-btn{padding:8px 16px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text2);cursor:pointer;font-size:13px;transition:.2s;text-decoration:none;display:inline-block;}
.tab-btn.active,.tab-btn:hover{background:var(--primary);color:#fff;border-color:var(--primary);}
.no-data{text-align:center;padding:40px;color:var(--text2);}
.no-data .icon{font-size:40px;margin-bottom:10px;}
input[type="file"]{display:none;}
.upload-zone{border:2px dashed var(--border);border-radius:12px;padding:30px;text-align:center;transition:.2s;cursor:pointer;}
.upload-zone:hover{border-color:var(--primary);background:rgba(242,122,26,.05);}
.product-img{width:40px;height:40px;object-fit:cover;border-radius:6px;background:var(--bg3);}
.api-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;}
.api-badge.ok{background:rgba(46,204,113,.15);color:var(--green);}
.api-badge.fail{background:rgba(231,76,60,.15);color:var(--red);}
.stat-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);}
.stat-row:last-child{border:none;}
.cost-chip{display:inline-flex;align-items:center;font-size:12px;font-weight:600;padding:3px 8px;border-radius:6px;}
.cost-chip.has{background:rgba(46,204,113,.1);color:var(--green);}
.cost-chip.no{background:rgba(144,153,196,.1);color:var(--text2);}
.tip{position:relative;cursor:default;}
.tip:hover .tip-box{display:block;}
.tip-box{display:none;position:absolute;bottom:120%;left:50%;transform:translateX(-50%);background:#1a1d2e;border:1px solid var(--border);border-radius:8px;padding:8px 12px;font-size:12px;white-space:nowrap;z-index:200;min-width:180px;}
.tip-box::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);border:5px solid transparent;border-top-color:#1a1d2e;}
#toast{position:fixed;bottom:20px;right:20px;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px 18px;font-size:13px;z-index:9999;display:none;max-width:350px;}
.hidden{display:none!important;}
</style>
</head>
<body>
<div id="toast"></div>

<div class="sidebar">
    <div class="logo">🛒 <?= htmlspecialchars($magaza['magaza_adi']) ?><span>Kar/Zarar Analizi</span></div>
    <div style="padding:0 20px 12px;border-bottom:1px solid var(--border);margin-bottom:8px">
        <div style="font-size:11px;color:var(--text2)">👤 <?= htmlspecialchars($authUser['ad'] ?: $authUser['email']) ?></div>
        <div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap">
            <?php if ($apiOk): ?><span class="api-badge ok">● API Bağlı</span><?php else: ?><span class="api-badge fail">● API Yok</span><?php endif; ?>
        </div>
        <div style="margin-top:8px;display:flex;gap:6px">
            <a href="magaza_sec.php" style="font-size:11px;color:var(--primary);text-decoration:none">🔄 Mağaza Değiştir</a>
            <?php if (isAdmin()): ?> · <a href="admin.php" style="font-size:11px;color:var(--text2);text-decoration:none">⚙️ Admin</a><?php endif; ?>
        </div>
    </div>
    <div class="sep">Analiz</div>
    <a href="?action=dashboard" class="<?= $action==='dashboard'?'active':'' ?>"><span>📊</span> Dashboard</a>
    <a href="?action=siparisler" class="<?= $action==='siparisler'?'active':'' ?>"><span>📦</span> Siparişler</a>
    <a href="?action=urunler" class="<?= $action==='urunler'?'active':'' ?>"><span>🏷️</span> Ürün Analizi</a>
    <a href="?action=kar_zarar" class="<?= $action==='kar_zarar'?'active':'' ?>"><span>📈</span> Kar/Zarar</a>
    <a href="?action=reklamlar" class="<?= $action==='reklamlar'?'active':'' ?>"><span>📣</span> Reklamlar</a>
    <a href="?action=odemeler" class="<?= $action==='odemeler'?'active':'' ?>"><span>💰</span> Ödemeler</a>
    <a href="?action=talepler" class="<?= $action==='talepler'?'active':'' ?>"><span>↩️</span> Talepler &amp; Sorular</a>
    <a href="?action=ai_analiz" class="<?= $action==='ai_analiz'?'active':'' ?>"><span>🤖</span> AI Analiz</a>
    <a href="?action=komisyon" class="<?= $action==='komisyon'?'active':'' ?>"><span>🏷️</span> Komisyon Tarifeleri</a>
    <div class="sep">Yönetim</div>
    <a href="?action=ty_urunler" class="<?= $action==='ty_urunler'?'active':'' ?>"><span>🔄</span> Trendyol Ürünleri</a>
    <a href="?action=veri_yukle" class="<?= $action==='veri_yukle'?'active':'' ?>"><span>📤</span> Veri Yükle</a>
    <a href="?action=ayarlar" class="<?= $action==='ayarlar'?'active':'' ?>"><span>⚙️</span> Ayarlar / API</a>
    <a href="logout.php" style="position:absolute;bottom:15px;left:0;right:0"><span>🚪</span> Çıkış Yap</a>
</div>

<div class="main">
<?php if ($dbError): ?>
<div class="alert alert-danger">
    <strong>Veritabanı bağlantı hatası:</strong> <?= htmlspecialchars($dbError) ?><br>
    <small>config.php dosyasındaki MySQL bilgilerini kontrol edin ve veritabanını oluşturun.</small>
</div>
<?php endif; ?>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($dbError): ?>
<!-- DB hata sayfası -->
<?php elseif ($action === 'dashboard'): ?>
<?php
// ── Ay seçimi ──
$selYil = (int)($_GET['yil'] ?? date('Y'));
$selAy  = (int)($_GET['ay']  ?? date('n'));

// Seçili ay KPI
$ayBaslangic = sprintf('%04d-%02d-01', $selYil, $selAy);
$ayBitis     = date('Y-m-t', strtotime($ayBaslangic));

$selAyStr  = sprintf('%02d', $selAy);
$selYilStr = sprintf('%04d', $selYil);

$ayKpi = DB::row("
    SELECT
        COUNT(*)                                   AS siparis_sayisi,
        COALESCE(SUM(siparis_tutari),0)            AS brut_ciro,
        COALESCE(SUM(ABS(komisyon)),0)             AS komisyon,
        COALESCE(SUM(ABS(gonderi_kargo)+ABS(iade_kargo)),0) AS kargo,
        COALESCE(SUM(ABS(platform_hizmet)),0)      AS platform,
        COALESCE(SUM(
            CASE WHEN net_tutar != 0 THEN net_tutar
                 ELSE siparis_tutari - ABS(komisyon) - ABS(gonderi_kargo) - ABS(iade_kargo) - ABS(platform_hizmet)
            END
        ),0) AS net_tutar,
        COALESCE(SUM(urun_adedi),0)                AS urun_adedi
    FROM siparisler
    WHERE magaza_id=?
      AND SUBSTRING(siparis_tarihi,4,2) = ?
      AND SUBSTRING(siparis_tarihi,7,4) = ?
      AND COALESCE(NULLIF(api_statusu,''), siparis_statusu) NOT LIKE '%İptal%'
      AND COALESCE(NULLIF(api_statusu,''), siparis_statusu) NOT LIKE '%Cancel%'
", [$magazaId, $selAyStr, $selYilStr]);

// Seçili ayın ürün maliyeti
$ayMaliyet = (float)DB::scalar("
    SELECT COALESCE(SUM(s.urun_adedi*(m.birim_maliyet+m.kargo_maliyeti+m.paket_maliyeti+m.diger_maliyet)),0)
    FROM siparisler s
    JOIN maliyetler m ON s.ty_urun_id=m.ty_urun_id AND m.magaza_id=s.magaza_id
    WHERE s.magaza_id=?
      AND SUBSTRING(s.siparis_tarihi,4,2) = ?
      AND SUBSTRING(s.siparis_tarihi,7,4) = ?
      AND s.siparis_statusu NOT LIKE '%İptal%'
      AND s.siparis_statusu NOT LIKE '%Cancel%'
", [$magazaId, $selAyStr, $selYilStr]);

$ayNet  = (float)($ayKpi['net_tutar'] ?? 0);
$ayKar  = $ayNet - $ayMaliyet;
$ayMarj = $ayNet > 0 ? round($ayKar / $ayNet * 100, 1) : 0;

// Seçili ay reklam harcaması
$ayReklam = 0.0;
try {
    $ayReklam = (float)DB::scalar("
        SELECT COALESCE(SUM(harcama),0) FROM reklamlar
        WHERE magaza_id=?
          AND (SUBSTRING(baslangic_tarihi,1,7)=? OR SUBSTRING(baslangic_tarihi,4,2)=? AND SUBSTRING(baslangic_tarihi,7,4)=?)
    ", [$magazaId, $selYilStr.'-'.$selAyStr, $selAyStr, $selYilStr]);
} catch(PDOException $e) {}
$ayKarReklamsiz = $ayKar;
$ayKarReklamli  = $ayKar - $ayReklam;

// Tüm aylar (veri olan)
$aylar = DB::rows("
    SELECT
        SUBSTRING(siparis_tarihi,7,4) AS yil,
        SUBSTRING(siparis_tarihi,4,2) AS ay,
        CONCAT(SUBSTRING(siparis_tarihi,7,4),'-',SUBSTRING(siparis_tarihi,4,2)) AS yyaa,
        COUNT(*)                      AS siparis_sayisi,
        SUM(siparis_tutari)           AS brut_ciro,
        SUM(ABS(komisyon))            AS komisyon,
        SUM(ABS(gonderi_kargo)+ABS(iade_kargo)) AS kargo,
        SUM(ABS(platform_hizmet))     AS platform,
        SUM(CASE WHEN net_tutar != 0 THEN net_tutar
                 ELSE siparis_tutari - ABS(komisyon) - ABS(gonderi_kargo) - ABS(iade_kargo) - ABS(platform_hizmet)
            END)                      AS net_tutar
    FROM siparisler
    WHERE magaza_id=?
      AND siparis_tarihi REGEXP '^[0-9]{2}\\.[0-9]{2}\\.[0-9]{4}'
      AND COALESCE(NULLIF(api_statusu,''), siparis_statusu) NOT LIKE '%İptal%'
      AND COALESCE(NULLIF(api_statusu,''), siparis_statusu) NOT LIKE '%Cancel%'
    GROUP BY yil, ay
    ORDER BY yil DESC, ay DESC
    LIMIT 24
", [$magazaId]);

// Ay maliyetlerini ayrı çek
$ayMaliyetMap = [];
$maliyetRows = DB::rows("
    SELECT
        SUBSTRING(s.siparis_tarihi,7,4) AS yil,
        SUBSTRING(s.siparis_tarihi,4,2) AS ay,
        COALESCE(SUM(s.urun_adedi*(m.birim_maliyet+m.kargo_maliyeti+m.paket_maliyeti+m.diger_maliyet)),0) AS toplam
    FROM siparisler s
    JOIN maliyetler m ON s.ty_urun_id=m.ty_urun_id AND m.magaza_id=s.magaza_id
    WHERE s.magaza_id=?
      AND s.siparis_tarihi REGEXP '^[0-9]{2}\\.[0-9]{2}\\.[0-9]{4}'
      AND s.siparis_statusu NOT LIKE '%İptal%'
      AND s.siparis_statusu NOT LIKE '%Cancel%'
    GROUP BY yil, ay
", [$magazaId]);
foreach ($maliyetRows as $r) {
    $ayMaliyetMap[$r['yil'].'-'.$r['ay']] = (float)$r['toplam'];
}

// Ay reklam harcama map'i
$ayReklamMap = [];
try {
    $reklamAyRows = DB::rows("
        SELECT
            CASE
                WHEN baslangic_tarihi REGEXP '^[0-9]{4}-[0-9]{2}'
                    THEN SUBSTRING(baslangic_tarihi,1,4)
                ELSE SUBSTRING(baslangic_tarihi,7,4)
            END AS yil,
            CASE
                WHEN baslangic_tarihi REGEXP '^[0-9]{4}-[0-9]{2}'
                    THEN SUBSTRING(baslangic_tarihi,6,2)
                ELSE SUBSTRING(baslangic_tarihi,4,2)
            END AS ay,
            COALESCE(SUM(harcama),0) AS toplam
        FROM reklamlar WHERE magaza_id=?
        GROUP BY yil, ay
    ", [$magazaId]);
    foreach ($reklamAyRows as $r) {
        $ayReklamMap[$r['yil'].'-'.$r['ay']] = (float)$r['toplam'];
    }
} catch(PDOException $e) {}

// Güncel yılın aylarını bul (ay seçici için)
$yillar = array_unique(array_column($aylar, 'yil'));
$secilebilirAylar = array_filter($aylar, fn($r)=>$r['yil']==$selYil);

$ayIsimleri = ['01'=>'Oca','02'=>'Şub','03'=>'Mar','04'=>'Nis','05'=>'May',
               '06'=>'Haz','07'=>'Tem','08'=>'Ağu','09'=>'Eyl','10'=>'Eki','11'=>'Kas','12'=>'Ara'];
?>

<!-- ═══ HEADER ═══ -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px">
    <div>
        <div style="font-size:20px;font-weight:600;color:var(--text)"><?= htmlspecialchars($magaza['magaza_adi']) ?></div>
        <div style="font-size:12px;color:var(--text2);margin-top:3px">
            <?php foreach ($yillar as $y): ?>
            <a href="?action=dashboard&yil=<?= $y ?>&ay=<?= $selYil==$y ? $selAy : date('n') ?>"
               style="color:<?= $y==$selYil?'var(--primary)':'var(--text2)' ?>;text-decoration:none;margin-right:8px;font-weight:<?= $y==$selYil?'600':'400' ?>"><?= $y ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <!-- Ay Seçici -->
    <div style="display:flex;gap:6px;flex-wrap:wrap">
        <?php foreach ($secilebilirAylar as $a):
            $isSelected = ((int)$a['ay'] === $selAy && (int)$a['yil'] === $selYil);
            $aaKey = $a['yil'].'-'.$a['ay'];
            $aMaliyet = $ayMaliyetMap[$aaKey] ?? 0;
            $aKar = (float)$a['net_tutar'] - $aMaliyet;
        ?>
        <a href="?action=dashboard&yil=<?= $a['yil'] ?>&ay=<?= (int)$a['ay'] ?>"
           style="padding:5px 12px;border-radius:8px;font-size:12px;text-decoration:none;
                  background:<?= $isSelected ? 'var(--primary)' : 'var(--bg3)' ?>;
                  color:<?= $isSelected ? '#fff' : ($aKar >= 0 ? 'var(--green)' : 'var(--red)') ?>;
                  border:1px solid <?= $isSelected ? 'var(--primary)' : ($aKar >= 0 ? 'rgba(46,204,113,.3)' : 'rgba(231,76,60,.3)') ?>">
            <?= $ayIsimleri[$a['ay']] ?? $a['ay'] ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- ═══ SEÇİLİ AY KPI ═══ -->
<div style="display:grid;grid-template-columns:1.6fr 1fr 1fr 1fr 1fr 1fr 1fr;gap:10px;margin-bottom:16px">
    <div style="background:linear-gradient(135deg,#ea580c,#f97316);border-radius:12px;padding:18px 20px">
        <div style="font-size:11px;color:rgba(255,255,255,.7);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">
            Brüt Ciro — <?= $ayIsimleri[sprintf('%02d',$selAy)] ?> <?= $selYil ?>
        </div>
        <div style="font-size:24px;font-weight:600;color:#fff"><?= fmtTL((float)($ayKpi['brut_ciro']??0)) ?></div>
        <div style="font-size:12px;color:rgba(255,255,255,.7);margin-top:4px">
            <?= fmt((float)($ayKpi['siparis_sayisi']??0),0) ?> sipariş · <?= fmt((float)($ayKpi['urun_adedi']??0),0) ?> adet
        </div>
    </div>
    <?php $karReklamsizPos = $ayKarReklamsiz >= 0; $karReklamliPos = $ayKarReklamli >= 0; ?>
    <div style="background:#27272a;border-radius:12px;padding:14px 16px;border-left:3px solid <?= $karReklamsizPos?'#22c55e':'#ef4444' ?>">
        <div style="font-size:10px;color:#71717a;text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px">Net Kar <small style="font-size:9px">(reklamsız)</small></div>
        <div style="font-size:17px;font-weight:600;color:<?= $karReklamsizPos?'#4ade80':'#f87171' ?>"><?= fmtTL($ayKarReklamsiz) ?></div>
        <div style="font-size:10px;color:#71717a;margin-top:3px">Marj <?= $ayMarj ?>%</div>
    </div>
    <div style="background:#27272a;border-radius:12px;padding:14px 16px;border-left:3px solid <?= $karReklamliPos?'#818cf8':'#ef4444' ?>">
        <div style="font-size:10px;color:#71717a;text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px">Net Kar <small style="font-size:9px">(reklamlı)</small></div>
        <div style="font-size:17px;font-weight:600;color:<?= $karReklamliPos?'#818cf8':'#f87171' ?>"><?= fmtTL($ayKarReklamli) ?></div>
        <div style="font-size:10px;color:#71717a;margin-top:3px">Reklam: -<?= fmtTL($ayReklam) ?></div>
    </div>
    <div style="background:#27272a;border-radius:12px;padding:14px 16px;border-left:3px solid #f97316">
        <div style="font-size:10px;color:#71717a;text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px">Reklam Harcaması</div>
        <div style="font-size:17px;font-weight:600;color:#f97316"><?= fmtTL($ayReklam) ?></div>
        <div style="font-size:10px;color:#71717a;margin-top:3px"><?= $ayReklam > 0 ? 'Ay toplam' : 'Veri yok' ?></div>
    </div>
    <div style="background:#27272a;border-radius:12px;padding:14px 16px;border-left:3px solid #ef4444">
        <div style="font-size:10px;color:#71717a;text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px">Komisyon</div>
        <div style="font-size:17px;font-weight:600;color:#f4f4f5"><?= fmtTL((float)($ayKpi['komisyon']??0)) ?></div>
        <div style="font-size:10px;color:#71717a;margin-top:3px">
            <?= ($ayKpi['brut_ciro']??0)>0 ? fmt((float)($ayKpi['komisyon']??0)/(float)$ayKpi['brut_ciro']*100,1) : 0 ?>% oran
        </div>
    </div>
    <div style="background:#27272a;border-radius:12px;padding:14px 16px;border-left:3px solid #f59e0b">
        <div style="font-size:10px;color:#71717a;text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px">Kargo</div>
        <div style="font-size:17px;font-weight:600;color:#f4f4f5"><?= fmtTL((float)($ayKpi['kargo']??0)) ?></div>
        <div style="font-size:10px;color:#71717a;margin-top:3px">Gönderim + iade</div>
    </div>
    <div style="background:#27272a;border-radius:12px;padding:14px 16px;border-left:3px solid #8b5cf6">
        <div style="font-size:10px;color:#71717a;text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px">Platform Hizmet</div>
        <div style="font-size:17px;font-weight:600;color:#f4f4f5"><?= fmtTL((float)($ayKpi['platform']??0)) ?></div>
        <div style="font-size:10px;color:#71717a;margin-top:3px">Trendyol kesintisi</div>
    </div>
</div>

<!-- ═══ AY KARŞILAŞTIRMA TABLOSU ═══ -->
<div style="background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:16px">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border);font-size:13px;font-weight:500;color:var(--text)">
        📅 Aylık Karşılaştırma
    </div>
    <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse">
    <thead>
        <tr style="background:var(--bg3)">
            <th style="text-align:left;padding:9px 16px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Ay</th>
            <th style="text-align:right;padding:9px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Sipariş</th>
            <th style="text-align:right;padding:9px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Brüt Ciro</th>
            <th style="text-align:right;padding:9px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Komisyon</th>
            <th style="text-align:right;padding:9px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Kargo</th>
            <th style="text-align:right;padding:9px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Platform</th>
            <th style="text-align:right;padding:9px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Net Tutar</th>
            <th style="text-align:right;padding:9px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Ürün Maliyeti</th>
            <th style="text-align:right;padding:9px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Reklam</th>
            <th style="text-align:right;padding:9px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Net Kar (reklamsız)</th>
            <th style="text-align:right;padding:9px 16px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Net Kar (reklamlı)</th>
            <th style="text-align:right;padding:9px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Ciro Marjı</th>
            <th style="text-align:right;padding:9px 16px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Maliyet Marjı</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($aylar as $a):
        $aaKey   = $a['yil'].'-'.$a['ay'];
        $aMali   = $ayMaliyetMap[$aaKey] ?? 0;
        $aReklam = $ayReklamMap[$aaKey] ?? 0;
        $aKarR   = (float)$a['net_tutar'] - $aMali;
        $aKarRek = $aKarR - $aReklam;
        $isSelAy= ((int)$a['ay']===$selAy && (int)$a['yil']===$selYil);
        $ayAdStr= ($ayIsimleri[$a['ay']] ?? $a['ay']).' '.$a['yil'];
    ?>
    <tr style="border-bottom:1px solid var(--border);background:<?= $isSelAy?'rgba(242,122,26,.06)':'' ?>;cursor:pointer"
        onclick="location.href='?action=dashboard&yil=<?= $a['yil'] ?>&ay=<?= (int)$a['ay'] ?>'">
        <td style="padding:10px 16px">
            <span style="font-weight:<?= $isSelAy?'600':'400' ?>;color:<?= $isSelAy?'var(--primary)':'var(--text)' ?>"><?= $ayAdStr ?></span>
            <?php if ($isSelAy): ?><span style="font-size:10px;color:var(--primary);margin-left:6px">●</span><?php endif; ?>
        </td>
        <td style="text-align:right;padding:10px 12px;font-size:13px"><?= fmt($a['siparis_sayisi'],0) ?></td>
        <td style="text-align:right;padding:10px 12px;font-size:13px;font-weight:500"><?= fmtTL((float)$a['brut_ciro']) ?></td>
        <td style="text-align:right;padding:10px 12px;font-size:13px;color:var(--red)"><?= fmtTL((float)$a['komisyon']) ?></td>
        <td style="text-align:right;padding:10px 12px;font-size:13px;color:var(--red)"><?= fmtTL((float)$a['kargo']) ?></td>
        <td style="text-align:right;padding:10px 12px;font-size:13px;color:var(--red)"><?= fmtTL((float)$a['platform']) ?></td>
        <td style="text-align:right;padding:10px 12px;font-size:13px;font-weight:500"><?= fmtTL((float)$a['net_tutar']) ?></td>
        <td style="text-align:right;padding:10px 12px;font-size:13px;color:var(--red)"><?= $aMali > 0 ? fmtTL($aMali) : '<span style="color:var(--text2)">—</span>' ?></td>
        <td style="text-align:right;padding:10px 12px;font-size:13px;color:var(--red)"><?= $aReklam > 0 ? fmtTL($aReklam) : '<span style="color:var(--text2)">—</span>' ?></td>
        <td style="text-align:right;padding:10px 12px;font-size:13px;font-weight:600">
            <?php if ($aMali > 0): ?>
                <span class="<?= $aKarR>=0?'positive':'negative' ?>"><?= fmtTL($aKarR) ?></span>
            <?php else: ?>
                <span style="color:var(--text2);font-size:11px">—</span>
            <?php endif; ?>
        </td>
        <td style="text-align:right;padding:10px 16px;font-size:13px;font-weight:600">
            <?php if ($aMali > 0): ?>
                <span class="<?= $aKarRek>=0?'positive':'negative' ?>"><?= fmtTL($aKarRek) ?></span>
                <?php if ($aReklam==0): ?><span style="font-size:9px;color:var(--text2);display:block">Reklam yok</span><?php endif; ?>
            <?php else: ?>
                <span style="color:var(--text2);font-size:11px">—</span>
            <?php endif; ?>
        </td>
        <?php
        $ciroMarji   = $a['brut_ciro'] > 0 ? round($aKarRek / $a['brut_ciro'] * 100, 1) : null;
        $maliyetMarji= $aMali > 0 ? round($aKarRek / $aMali * 100, 1) : null;
        ?>
        <td style="text-align:right;padding:10px 12px;font-size:13px">
            <?php if ($ciroMarji !== null): ?>
                <span class="<?= $ciroMarji>=0?'positive':'negative' ?>" style="font-weight:500"><?= $ciroMarji ?>%</span>
            <?php else: ?><span style="color:var(--text2)">—</span><?php endif; ?>
        </td>
        <td style="text-align:right;padding:10px 16px;font-size:13px">
            <?php if ($maliyetMarji !== null): ?>
                <span class="<?= $maliyetMarji>=0?'positive':'negative' ?>" style="font-weight:500"><?= $maliyetMarji ?>%</span>
            <?php else: ?><span style="color:var(--text2)">—</span><?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <!-- Toplam satırı -->
    <?php
    $totCiro  = array_sum(array_column($aylar,'brut_ciro'));
    $totKom   = array_sum(array_column($aylar,'komisyon'));
    $totKarg  = array_sum(array_column($aylar,'kargo'));
    $totNet   = array_sum(array_column($aylar,'net_tutar'));
    // Sadece gösterilen aylara ait maliyet ve reklamı topla
    $totMal   = 0; $totReklam = 0;
    foreach ($aylar as $_a) {
        $_key = $_a['yil'].'-'.$_a['ay'];
        $totMal    += $ayMaliyetMap[$_key] ?? 0;
        $totReklam += $ayReklamMap[$_key] ?? 0;
    }
    $totKar   = $totNet - $totMal;
    $totKarRek= $totKar - $totReklam;
    ?>
    <tr style="background:var(--bg3);border-top:2px solid var(--border);font-weight:600">
        <td style="padding:10px 16px;color:var(--text)">Toplam (<?= count($aylar) ?> ay)</td>
        <td style="text-align:right;padding:10px 12px"><?= fmt(array_sum(array_column($aylar,'siparis_sayisi')),0) ?></td>
        <td style="text-align:right;padding:10px 12px"><?= fmtTL($totCiro) ?></td>
        <td style="text-align:right;padding:10px 12px;color:var(--red)"><?= fmtTL($totKom) ?></td>
        <td style="text-align:right;padding:10px 12px;color:var(--red)"><?= fmtTL($totKarg) ?></td>
        <td style="text-align:right;padding:10px 12px;color:var(--red)"><?= fmtTL(array_sum(array_column($aylar,'platform'))) ?></td>
        <td style="text-align:right;padding:10px 12px"><?= fmtTL($totNet) ?></td>
        <td style="text-align:right;padding:10px 12px;color:var(--red)"><?= $totMal>0?fmtTL($totMal):'—' ?></td>
        <td style="text-align:right;padding:10px 12px;color:var(--red)"><?= $totReklam>0?fmtTL($totReklam):'—' ?></td>
        <td style="text-align:right;padding:10px 12px">
            <?php if ($totMal > 0): ?><span class="<?= $totKar>=0?'positive':'negative' ?>"><?= fmtTL($totKar) ?></span>
            <?php else: ?><span style="color:var(--text2)">—</span><?php endif; ?>
        </td>
        <td style="text-align:right;padding:10px 16px">
            <?php if ($totMal > 0): ?><span class="<?= $totKarRek>=0?'positive':'negative' ?>"><?= fmtTL($totKarRek) ?></span>
            <?php else: ?><span style="color:var(--text2)">—</span><?php endif; ?>
        </td>
        <?php
        $totCiroMarji   = $totCiro > 0 ? round($totKarRek / $totCiro * 100, 1) : null;
        $totMaliyetMarji= $totMal  > 0 ? round($totKarRek / $totMal  * 100, 1) : null;
        ?>
        <td style="text-align:right;padding:10px 12px;font-weight:600">
            <?php if ($totCiroMarji!==null): ?><span class="<?= $totCiroMarji>=0?'positive':'negative' ?>"><?= $totCiroMarji ?>%</span>
            <?php else: ?><span style="color:var(--text2)">—</span><?php endif; ?>
        </td>
        <td style="text-align:right;padding:10px 16px;font-weight:600">
            <?php if ($totMaliyetMarji!==null): ?><span class="<?= $totMaliyetMarji>=0?'positive':'negative' ?>"><?= $totMaliyetMarji ?>%</span>
            <?php else: ?><span style="color:var(--text2)">—</span><?php endif; ?>
        </td>
    </tr>
    </tbody>
    </table>
    </div>
</div>

<!-- ═══ ALT KISIM: Top ürünler + Sipariş statüsü ═══ -->
<?php
$topUrunler = [];
try {
    $topUrunler = DB::rows("
        SELECT tu.title, tu.image_url, tu.barcode,
               COUNT(DISTINCT s.id) AS siparis_sayisi,
               SUM(s.urun_adedi)    AS adet,
               SUM(s.siparis_tutari) AS ciro,
               SUM(s.net_tutar)     AS net
        FROM trendyol_urunler tu
        JOIN siparisler s ON s.ty_urun_id=tu.ty_id AND s.magaza_id=tu.magaza_id
        WHERE tu.magaza_id=?
          AND SUBSTRING(s.siparis_tarihi,4,2) = ?
          AND SUBSTRING(s.siparis_tarihi,7,4) = ?
          AND s.siparis_statusu NOT LIKE '%İptal%'
        GROUP BY tu.ty_id ORDER BY adet DESC LIMIT 5
    ", [$magazaId, $selAyStr, $selYilStr]);
} catch(PDOException $e) {}

$_rawStat2 = DB::rows("
    SELECT CASE WHEN api_statusu IS NOT NULL AND api_statusu != '' THEN api_statusu
                ELSE siparis_statusu END AS raw_s,
           COUNT(*) as adet
    FROM siparisler
    WHERE magaza_id=?
      AND SUBSTRING(siparis_tarihi,4,2) = ?
      AND SUBSTRING(siparis_tarihi,7,4) = ?
    GROUP BY raw_s ORDER BY adet DESC
", [$magazaId, $selAyStr, $selYilStr]);
$_statMapDash = [
    'UnDeliveredAndReturned'=>'İade Edildi','UnPacked'=>'Hazırlanıyor',
    'AtCollectionPoint'=>'Teslim Noktasında','Shipped'=>'Kargoya Verildi',
    'Delivered'=>'Teslim Edildi','Cancelled'=>'İptal Edildi','Created'=>'Yeni Sipariş',
    'Picking'=>'Hazırlanıyor','Returned'=>'İade Edildi','ReadyToShip'=>'Kargoya Hazır',
    'InTransit'=>'Kargoda',
];
$_grp2 = [];
foreach ($_rawStat2 as $_r) {
    $_tr = $_statMapDash[$_r['raw_s']] ?? $_r['raw_s'];
    $_grp2[$_tr] = ($_grp2[$_tr] ?? 0) + (int)$_r['adet'];
}
arsort($_grp2);
$statusData2 = [];
foreach ($_grp2 as $_s => $_n) $statusData2[] = ['siparis_statusu'=>$_s,'adet'=>$_n,'toplam'=>0];
$totStatus = array_sum(array_column($statusData2,'adet')) ?: 1;
?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
    <!-- En Çok Satan -->
    <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px">
        <div style="font-size:13px;font-weight:500;color:var(--text);margin-bottom:12px">
            🏆 En Çok Satan — <?= $ayIsimleri[sprintf('%02d',$selAy)] ?>
        </div>
        <?php if (empty($topUrunler)): ?>
        <div style="color:var(--text2);font-size:12px;text-align:center;padding:20px">Bu ayda satış yok</div>
        <?php else: $mxA = max(array_column($topUrunler,'adet')) ?: 1; foreach ($topUrunler as $i=>$u): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:0.5px solid var(--border)">
            <div style="font-size:12px;font-weight:600;color:var(--text2);width:16px;flex-shrink:0"><?= $i+1 ?></div>
            <?php if ($u['image_url']): ?><img src="<?= htmlspecialchars($u['image_url']) ?>" style="width:30px;height:30px;object-fit:cover;border-radius:5px;flex-shrink:0"><?php endif; ?>
            <div style="flex:1;min-width:0">
                <div style="font-size:12px;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars(substr($u['title'],0,35)) ?></div>
                <div style="display:flex;gap:6px;margin-top:3px;align-items:center">
                    <div style="flex:1;background:var(--bg3);border-radius:2px;height:3px"><div style="width:<?= round($u['adet']/$mxA*100) ?>%;height:100%;background:var(--primary);border-radius:2px"></div></div>
                    <span style="font-size:11px;color:var(--text2);flex-shrink:0"><?= fmt($u['adet'],0) ?> adet</span>
                </div>
            </div>
            <div style="font-size:12px;font-weight:500;color:var(--text);flex-shrink:0"><?= fmtTL($u['ciro']) ?></div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Sipariş Statüsü -->
    <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px">
        <div style="font-size:13px;font-weight:500;color:var(--text);margin-bottom:12px">
            📋 Sipariş Statüsü — <?= $ayIsimleri[sprintf('%02d',$selAy)] ?>
        </div>
        <?php
        $stRenkler = ['Teslim Edildi'=>'#4ade80','İptal Edildi'=>'#f87171','İade Edildi'=>'#fbbf24','Yeni Sipariş'=>'#60a5fa','Delivered'=>'#4ade80','Cancelled'=>'#f87171'];
        foreach ($statusData2 as $s):
            $renk = $stRenkler[$s['siparis_statusu']] ?? '#a1a1aa';
            $pct  = round($s['adet']/$totStatus*100);
        ?>
        <div style="margin-bottom:10px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                <span style="font-size:12px;color:var(--text2)"><?= htmlspecialchars($s['siparis_statusu']??'?') ?></span>
                <div style="text-align:right">
                    <span style="font-size:13px;font-weight:500;color:var(--text)"><?= fmt($s['adet'],0) ?></span>
                    <span style="font-size:11px;color:var(--text2);margin-left:4px"><?= fmtTL($s['toplam']) ?></span>
                </div>
            </div>
            <div style="background:var(--bg3);border-radius:3px;height:5px;overflow:hidden">
                <div style="width:<?= $pct ?>%;height:100%;background:<?= $renk ?>;border-radius:3px"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php elseif ($action === 'ty_urunler'): ?>
<?php
$search      = trim($_GET['q'] ?? '');
$maliyetFilt = $_GET['maliyet'] ?? '';   // 'yok' | 'var' | ''
$page        = max(1, (int)($_GET['p'] ?? 1));
$perPage     = 30;
$offset      = ($page - 1) * $perPage;

$conds  = ["tu.magaza_id = ?"]; 
$params = [$magazaId];

if ($search) {
    $conds[]  = "(tu.title LIKE ? OR tu.barcode LIKE ? OR tu.stock_code LIKE ?)";
    $params   = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($maliyetFilt === 'yok') {
    $conds[] = "m.birim_maliyet IS NULL";
} elseif ($maliyetFilt === 'var') {
    $conds[] = "m.birim_maliyet IS NOT NULL";
}

$where = $conds ? "WHERE " . implode(" AND ", $conds) : "";

$total    = (int)DB::scalar(
    "SELECT COUNT(*) FROM trendyol_urunler tu
     LEFT JOIN maliyetler m ON tu.ty_id=m.ty_urun_id AND m.magaza_id=tu.magaza_id $where", $params);
$tyUrunler= DB::rows(
    "SELECT tu.*, m.birim_maliyet, m.kargo_maliyeti, m.paket_maliyeti, m.diger_maliyet, m.id as m_id
     FROM trendyol_urunler tu
     LEFT JOIN maliyetler m ON tu.ty_id=m.ty_urun_id AND m.magaza_id=tu.magaza_id
     $where ORDER BY tu.title LIMIT $perPage OFFSET $offset", $params);
$pages    = (int)ceil($total / $perPage);
$lastSync = DB::scalar("SELECT MAX(cekme_tarihi) FROM trendyol_urunler WHERE magaza_id=?",[$magazaId]);

// Sayaçlar
$cntVar = (int)DB::scalar("SELECT COUNT(*) FROM trendyol_urunler tu JOIN maliyetler m ON tu.ty_id=m.ty_urun_id AND m.magaza_id=tu.magaza_id WHERE tu.magaza_id=?",[$magazaId]);
$cntYok = (int)DB::scalar("SELECT COUNT(*) FROM trendyol_urunler tu LEFT JOIN maliyetler m ON tu.ty_id=m.ty_urun_id AND m.magaza_id=tu.magaza_id WHERE tu.magaza_id=? AND m.ty_urun_id IS NULL",[$magazaId]);
?>
<div class="page-title">🔄 <span>Trendyol Ürünleri</span>
    <span style="float:right;display:flex;gap:10px;align-items:center">
        <?php if ($lastSync): ?><span style="font-size:12px;color:var(--text2)">Son senkronizasyon: <?= $lastSync ?></span><?php endif; ?>
        <?php if ($apiOk): ?>
        <button class="btn btn-primary btn-sm" onclick="syncProducts()">🔄 API'den Senkronize Et</button>
        <button class="btn btn-success btn-sm" onclick="rematch()">🔗 Eşleştirmeyi Güncelle</button>
        <?php else: ?>
        <a href="?action=ayarlar" class="btn btn-sm" style="background:var(--bg3);color:var(--text2)">⚙️ API Ayarla</a>
        <?php endif; ?>
    </span>
</div>

<?php if (!$apiOk): ?>
<div class="alert alert-warning">⚠️ Trendyol API bilgileri <a href="?action=ayarlar" style="color:var(--primary)">config.php</a>'ye girilmemiş. Ürünleri görmek için API bilgilerini ekleyin.</div>
<?php endif; ?>

<div class="card" style="padding:15px 20px;margin-bottom:15px">
<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <input type="hidden" name="action" value="ty_urunler">
    <div class="form-group" style="flex:1;min-width:200px;margin:0">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Ürün adı, barkod, stok kodu...">
    </div>
    <input type="hidden" name="maliyet" value="<?= htmlspecialchars($maliyetFilt) ?>">
    <button type="submit" class="btn btn-primary">🔍 Ara</button>
    <?php if ($search || $maliyetFilt): ?>
    <a href="?action=ty_urunler" class="btn" style="background:var(--bg3);color:var(--text2)">✕ Temizle</a>
    <?php endif; ?>
    <span style="align-self:center;color:var(--text2);font-size:12px;white-space:nowrap"><?= $total ?> ürün</span>
</form>
<!-- Maliyet filtre butonları -->
<div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
    <span style="font-size:12px;color:var(--text2);align-self:center">Maliyet:</span>
    <a href="?action=ty_urunler&q=<?= urlencode($search) ?>"
       class="tab-btn <?= $maliyetFilt==='' ? 'active' : '' ?>" style="padding:5px 12px;font-size:12px">
       Tümü (<?= $cntVar+$cntYok ?>)
    </a>
    <a href="?action=ty_urunler&q=<?= urlencode($search) ?>&maliyet=yok"
       class="tab-btn <?= $maliyetFilt==='yok' ? 'active' : '' ?>"
       style="padding:5px 12px;font-size:12px;<?= $maliyetFilt==='yok'?'':'border-color:var(--red);color:var(--red)' ?>">
       ❌ Maliyetsiz (<?= $cntYok ?>)
    </a>
    <a href="?action=ty_urunler&q=<?= urlencode($search) ?>&maliyet=var"
       class="tab-btn <?= $maliyetFilt==='var' ? 'active' : '' ?>"
       style="padding:5px 12px;font-size:12px;<?= $maliyetFilt==='var'?'':'border-color:var(--green);color:var(--green)' ?>">
       ✅ Maliyetli (<?= $cntVar ?>)
    </a>
</div>
</div>

<div class="card" style="padding:0;overflow:hidden">
<?php if (empty($tyUrunler)): ?>
<div class="no-data"><div class="icon"><?= $apiOk ? '🔄' : '🔌' ?></div>
<p><?= $apiOk ? 'Henüz senkronizasyon yapılmamış. "API\'den Senkronize Et" butonuna tıklayın.' : 'API bilgileri girilmemiş.' ?></p></div>
<?php else: ?>
<div style="overflow-x:auto"><table>
<thead><tr>
    <th>Görsel</th><th>Ürün Adı / Barkod</th><th>Kategori</th><th>Marka</th>
    <th>Renk / Beden</th>
    <th style="text-align:right">Liste Fiyatı</th>
    <th style="text-align:right">Satış Fiyatı</th>
    <th style="text-align:right">Stok</th>
    <th>Durum</th>
    <th style="text-align:right">Birim Maliyet</th>
    <th>Eşleşme</th>
</tr></thead>
<tbody>
<?php foreach ($tyUrunler as $u):
    $birimM = $u['birim_maliyet'] ? ((float)$u['birim_maliyet']+(float)$u['kargo_maliyeti']+(float)$u['paket_maliyeti']+(float)$u['diger_maliyet']) : null;
    $eslesme = DB::scalar("SELECT COUNT(*) FROM urun_satis WHERE magaza_id=? AND ty_urun_id=?", [$magazaId, $u['ty_id']]);
?>
<tr>
    <td><?php if ($u['image_url']): ?><img src="<?= htmlspecialchars($u['image_url']) ?>" class="product-img" loading="lazy"><?php else: ?><div class="product-img" style="display:flex;align-items:center;justify-content:center;font-size:18px">📦</div><?php endif; ?></td>
    <td style="max-width:200px">
        <div style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px" title="<?= htmlspecialchars($u['title']) ?>"><?= htmlspecialchars($u['title']) ?></div>
        <div style="font-size:11px;color:var(--text2);font-family:monospace"><?= htmlspecialchars($u['barcode']??'') ?></div>
        <div style="font-size:10px;color:var(--border)">SC: <?= htmlspecialchars($u['stock_code']??'') ?></div>
    </td>
    <td><span class="badge badge-blue" style="font-size:10px"><?= htmlspecialchars($u['category_name']??'-') ?></span></td>
    <td style="font-size:12px;color:var(--text2)"><?= htmlspecialchars($u['brand']??'-') ?></td>
    <td style="font-size:12px">
        <?php if ($u['color']): ?><span class="badge badge-gray"><?= htmlspecialchars($u['color']) ?></span><?php endif; ?>
        <?php if ($u['size']): ?><span class="badge badge-gray" style="margin-left:3px"><?= htmlspecialchars($u['size']) ?></span><?php endif; ?>
    </td>
    <td style="text-align:right;font-size:12px;color:var(--text2)"><?= fmtTL($u['list_price']) ?></td>
    <td style="text-align:right;font-weight:600"><?= fmtTL($u['sale_price']) ?></td>
    <td style="text-align:right"><?= fmt($u['quantity'],0) ?></td>
    <td><?= $u['approved'] ? '<span class="badge badge-green">Onaylı</span>' : '<span class="badge badge-yellow">Onaysız</span>' ?></td>
    <td style="text-align:right">
    <?php if ($birimM !== null): ?>
        <div class="tip"><span class="cost-chip has"><?= fmtTL($birimM) ?></span>
        <div class="tip-box">Ürün: <?= fmtTL($u['birim_maliyet']) ?><br>Kargo: <?= fmtTL($u['kargo_maliyeti']) ?><br>Paket: <?= fmtTL($u['paket_maliyeti']) ?><br>Diğer: <?= fmtTL($u['diger_maliyet']) ?></div></div>
        <a href="#" onclick="deleteCost(<?= $u['m_id'] ?>,this);return false" style="color:var(--red);font-size:10px;margin-left:4px">✕</a>
    <?php else: ?>
        <button class="btn btn-sm" style="background:var(--bg3);color:var(--text2);font-size:10px" onclick="openCostModal('<?= htmlspecialchars($u['ty_id'],ENT_QUOTES) ?>','<?= htmlspecialchars($u['barcode']??'',ENT_QUOTES) ?>','<?= htmlspecialchars(addslashes($u['title']??'')) ?>')">+ Maliyet</button>
    <?php endif; ?>
    </td>
    <td style="font-size:11px">
    <?php if ($eslesme > 0): ?>
        <span class="badge badge-green"><?= $eslesme ?> rapor</span>
    <?php else: ?>
        <span class="badge badge-gray">Eşleşmedi</span>
    <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<!-- Pagination -->
<?php if ($pages > 1): ?>
<div style="padding:15px 20px;display:flex;gap:8px;flex-wrap:wrap">
    <?php for ($i=1;$i<=$pages;$i++): ?>
    <a href="?action=ty_urunler&p=<?= $i ?>&q=<?= urlencode($search) ?>&maliyet=<?= urlencode($maliyetFilt) ?>" class="tab-btn <?= $i===$page?'active':'' ?>" style="padding:5px 10px;font-size:12px"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
</div>

<!-- Maliyet Modal -->
<div id="costModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;align-items:center;justify-content:center">
<div style="background:var(--card);border:1px solid var(--border);border-radius:12px;padding:25px;width:400px;max-width:90vw">
    <h3 style="margin-bottom:15px">💰 Maliyet Ekle</h3>
    <input type="hidden" id="m_ty_id"><input type="hidden" id="m_barcode">
    <div id="m_urun_adi" style="color:var(--text2);font-size:12px;margin-bottom:15px"></div>
    <div class="form-grid" style="grid-template-columns:1fr 1fr">
        <div class="form-group"><label>Birim Maliyet ₺ *</label><input type="number" id="m_birim" step="0.01" min="0" placeholder="0.00"></div>
        <div class="form-group"><label>Kargo Maliyeti ₺</label><input type="number" id="m_kargo" step="0.01" min="0" value="0"></div>
        <div class="form-group"><label>Paketleme ₺</label><input type="number" id="m_paket" step="0.01" min="0" value="0"></div>
        <div class="form-group"><label>Diğer ₺</label><input type="number" id="m_diger" step="0.01" min="0" value="0"></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:15px">
        <button class="btn btn-primary" onclick="saveCost()">💾 Kaydet</button>
        <button class="btn" style="background:var(--bg3);color:var(--text2)" onclick="document.getElementById('costModal').style.display='none'">İptal</button>
    </div>
</div>
</div>

<?php endif; ?>
<?php elseif ($action === 'siparisler'): ?>
<?php
$filter  = $_GET['filter'] ?? '';
$srch    = trim($_GET['q'] ?? '');
$sort    = $_GET['sort'] ?? 'desc';   // desc = yeniden eskiye, asc = eskiden yeniye
$sort    = in_array($sort, ['desc','asc']) ? $sort : 'desc';
$pg      = max(1,(int)($_GET['p'] ?? 1));
$pp      = 100;
$off     = ($pg-1)*$pp;
$conds   = ["s.magaza_id = ?"];
$prms    = [$magazaId];
if ($filter) {
    // Türkçe filtre → İngilizce API karşılıklarını bul, hepsini sorgula
    $filterMap = [
        'İade Edildi'          => ['İade Edildi','UnDeliveredAndReturned','Returned'],
        'Kargoya Verildi'      => ['Kargoya Verildi','Shipped'],
        'Kargoya Hazır'        => ['Kargoya Hazır','ReadyToShip'],
        'Teslim Edildi'        => ['Teslim Edildi','Delivered'],
        'İptal Edildi'         => ['İptal Edildi','Cancelled'],
        'Hazırlanıyor'         => ['Hazırlanıyor','Picking','UnPacked'],
        'Yeni Sipariş'         => ['Yeni Sipariş','Created'],
        'Teslim Noktasında'    => ['Teslim Noktasında','AtCollectionPoint'],
        'Kargoda'              => ['Kargoda','InTransit'],
    ];
    $vals = $filterMap[$filter] ?? [$filter];
    $ph   = implode(',', array_fill(0, count($vals), '?'));
    // api_statusu varsa onu, yoksa siparis_statusu'nu kontrol et
    $conds[] = "(CASE WHEN s.api_statusu IS NOT NULL AND s.api_statusu != '' THEN s.api_statusu ELSE s.siparis_statusu END) IN ($ph)";
    $prms = array_merge($prms, $vals);
}
if ($srch)   { $conds[] = '(s.siparis_no LIKE ? OR s.musteri LIKE ? OR tu.title LIKE ?)'; $prms = array_merge($prms, ["%$srch%","%$srch%","%$srch%"]); }
$where   = 'WHERE '.implode(' AND ',$conds);
$totSip  = (int)DB::scalar("SELECT COUNT(*) FROM siparisler s LEFT JOIN trendyol_urunler tu ON s.ty_urun_id=tu.ty_id AND tu.magaza_id=s.magaza_id $where", $prms);
// Siparis satirlari özetini ayrı sorguyla al (GROUP BY sorunu olmasin)
$satirOzetMap = [];
try {
    $satirOzet = DB::rows("
        SELECT ss.siparis_no,
            COUNT(DISTINCT ss.barcode) AS satir_sayisi,
            GROUP_CONCAT(DISTINCT ss.product_name ORDER BY ss.kalem_tutari DESC SEPARATOR '|') AS api_urun_adlari,
            GROUP_CONCAT(DISTINCT tu.image_url ORDER BY ss.kalem_tutari DESC SEPARATOR '|')   AS api_gorseller,
            GROUP_CONCAT(DISTINCT tu.title      ORDER BY ss.kalem_tutari DESC SEPARATOR '|')  AS api_basliklar
        FROM siparis_satirlari ss
        LEFT JOIN trendyol_urunler tu ON tu.barcode = ss.barcode AND tu.magaza_id = ss.magaza_id
        WHERE ss.magaza_id = ?
        GROUP BY ss.siparis_no
    ", [$magazaId]);
    foreach ($satirOzet as $so) $satirOzetMap[$so['siparis_no']] = $so;
} catch(Exception $e) { /* tablo yoksa geç */ }

$orders  = DB::rows("SELECT s.*,
    tu.title as ty_title, tu.image_url, tu.barcode as tu_barcode,
    m.birim_maliyet, m.kargo_maliyeti, m.paket_maliyeti, m.diger_maliyet,
    od.vade_tarihi AS od_vade_tarihi,
    od.islem_tipi  AS od_islem_tipi
    FROM siparisler s
    LEFT JOIN trendyol_urunler tu ON s.ty_urun_id = tu.ty_id AND tu.magaza_id = s.magaza_id
    LEFT JOIN maliyetler m ON tu.ty_id = m.ty_urun_id AND m.magaza_id = s.magaza_id
    LEFT JOIN (
        SELECT siparis_no, magaza_id,
               MAX(vade_tarihi) AS vade_tarihi,
               GROUP_CONCAT(DISTINCT islem_tipi ORDER BY islem_tipi SEPARATOR ',') AS islem_tipi
        FROM odeme_detay
        GROUP BY siparis_no, magaza_id
    ) od ON od.siparis_no = s.siparis_no AND od.magaza_id = s.magaza_id
    $where ORDER BY
        CASE
            WHEN s.siparis_tarihi REGEXP '^[0-9]{2}\\.[0-9]{2}\\.[0-9]{4}'
                THEN STR_TO_DATE(SUBSTRING(s.siparis_tarihi,1,10), '%d.%m.%Y')
            WHEN s.siparis_tarihi REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}'
                THEN STR_TO_DATE(SUBSTRING(s.siparis_tarihi,1,10), '%Y-%m-%d')
            ELSE NULL
        END $sort,
        s.id $sort
    LIMIT $pp OFFSET $off", $prms);
// Satır özetlerini merge et
foreach ($orders as &$o) {
    $oz = $satirOzetMap[$o['siparis_no']] ?? null;
    $o['api_urun_adlari'] = $oz['api_urun_adlari'] ?? null;
    $o['satir_sayisi']    = (int)($oz['satir_sayisi'] ?? 0);
    $o['api_gorseller']   = $oz['api_gorseller']   ?? null;
    $o['api_basliklar']   = $oz['api_basliklar']   ?? null;
}
unset($o);
$pages   = (int)ceil($totSip/$pp);

// Sayfadaki siparişler için kalem detaylarını çek
$satirDetayMap = [];
if (!empty($orders)) {
    $noList = array_map(fn($o)=>$o['siparis_no'], $orders);
    $inPh   = implode(',', array_fill(0, count($noList), '?'));
    try {
        $kalemler = DB::rows("
            SELECT ss.siparis_no, ss.barcode, ss.product_name, ss.adet,
                   ss.birim_fiyat, ss.kalem_tutari, ss.ty_urun_id,
                   tu.image_url, tu.title,
                   m.birim_maliyet, m.kargo_maliyeti, m.paket_maliyeti, m.diger_maliyet,
                   (m.birim_maliyet+m.kargo_maliyeti+m.paket_maliyeti+m.diger_maliyet) AS birim_toplam
            FROM siparis_satirlari ss
            LEFT JOIN trendyol_urunler tu ON tu.barcode=ss.barcode AND tu.magaza_id=ss.magaza_id
            LEFT JOIN maliyetler m ON m.ty_urun_id=tu.ty_id AND m.magaza_id=ss.magaza_id
            WHERE ss.magaza_id=? AND ss.siparis_no IN ($inPh)
            ORDER BY ss.siparis_no, ss.kalem_tutari DESC
        ", array_merge([$magazaId], $noList));
        foreach ($kalemler as $k) {
            $satirDetayMap[$k['siparis_no']][] = $k;
        }
    } catch(PDOException $e) {}
}

$eslesme = $stats['eslesme_siparis'];

// ---- Mevcut filtre/arama ile TOPLAM özet (tüm sayfalar) ----
// NOT: Kar hesabı Kar/Zarar sayfasıyla tutarlı olsun diye urun_satis tablosundan alınır.
// Sipariş tutarı/komisyon/kargo ise siparisler tablosundan gelir.
$ozet = DB::row("SELECT
    COUNT(*)                                            AS siparis_sayisi,
    COALESCE(SUM(s.siparis_tutari), 0)                  AS toplam_tutar,
    COALESCE(SUM(ABS(s.komisyon)), 0)                   AS toplam_komisyon,
    COALESCE(SUM(ABS(s.gonderi_kargo)+ABS(s.iade_kargo)), 0) AS toplam_kargo,
    COALESCE(SUM(ABS(s.platform_hizmet)), 0)            AS toplam_platform,
    COALESCE(SUM(s.net_tutar), 0)                       AS toplam_net,
    COALESCE(SUM(
        CASE WHEN m.birim_maliyet IS NOT NULL
          AND s.siparis_statusu NOT LIKE '%İptal%'
          AND s.siparis_statusu NOT LIKE '%Cancel%'
        THEN (m.birim_maliyet+m.kargo_maliyeti+m.paket_maliyeti+m.diger_maliyet)*s.urun_adedi
        ELSE 0 END
    ), 0)                                               AS toplam_maliyet,
    COUNT(CASE WHEN m.birim_maliyet IS NOT NULL
      AND s.siparis_statusu NOT LIKE '%İptal%'
      AND s.siparis_statusu NOT LIKE '%Cancel%'
    THEN 1 END) AS maliyet_olan
    FROM siparisler s
    LEFT JOIN trendyol_urunler tu ON s.ty_urun_id = tu.ty_id AND tu.magaza_id = s.magaza_id
    LEFT JOIN maliyetler m        ON tu.ty_id = m.ty_urun_id AND m.magaza_id = s.magaza_id
    $where", $prms);

// Kar/Zarar sayfasıyla tutarlı toplam kar: urun_satis bazlı
$karAnalizOzet = DB::row("SELECT
    COALESCE(SUM(u.net_ciro - u.toplam_komisyon), 0) AS net_gelir,
    COALESCE(SUM(
        (m.birim_maliyet+m.kargo_maliyeti+m.paket_maliyeti+m.diger_maliyet)*u.net_satis
    ), 0) AS toplam_maliyet_us
    FROM urun_satis u
    JOIN maliyetler m ON u.ty_urun_id = m.ty_urun_id AND m.magaza_id = u.magaza_id
    WHERE u.magaza_id = ?", [$magazaId]);

$ozToplam      = (float)($ozet['toplam_tutar']    ?? 0);
$ozKomisyon    = (float)($ozet['toplam_komisyon'] ?? 0);
$ozKargo       = (float)($ozet['toplam_kargo']    ?? 0);
$ozPlatform    = (float)($ozet['toplam_platform'] ?? 0);
$ozNet         = (float)($ozet['toplam_net']      ?? 0);
$ozMaliyet     = (float)($ozet['toplam_maliyet']  ?? 0);
// Kar = Kar/Zarar sayfasıyla aynı formül (urun_satis bazlı)
$ozKarNetGelir = (float)($karAnalizOzet['net_gelir']         ?? 0);
$ozKarMaliyet  = (float)($karAnalizOzet['toplam_maliyet_us'] ?? 0);
// ozKarNetGelir = urun_satis bazlı (komisyon düşülmüş, kargo/platform düşülmemiş)
// Bu kaynakta kargo+platform düşülmeli
// Ancak ozNet (siparisler.net_tutar) zaten hepsini kapsıyor
// Tutarlı olması için ozNet bazlı hesap yapalım:
$ozKar         = $ozNet - $ozMaliyet;
$ozKarMarji    = $ozKarNetGelir > 0 ? round($ozKar / $ozKarNetGelir * 100, 1) : 0;
$ozMaliyetOlan = (int)($ozet['maliyet_olan'] ?? 0);

// Trendyol ürün listesi (dropdown için) — fiyata göre sıralı
$tyDropdown = DB::rows("SELECT ty_id, barcode, title, sale_price FROM trendyol_urunler WHERE magaza_id=? ORDER BY sale_price, title LIMIT 2000", [$magazaId]);
// Her birim fiyat için kaç ürün var → belirsizlik tespiti
$fiyatSayaci = [];
foreach (DB::rows("SELECT ROUND(sale_price,2) as fiyat, COUNT(*) as adet FROM trendyol_urunler WHERE magaza_id=? AND sale_price>0 GROUP BY fiyat", [$magazaId]) as $fs) {
    $fiyatSayaci[(string)$fs['fiyat']] = (int)$fs['adet'];
}
?>
<div class="page-title">📦 <span>Siparişler</span>
    <?php if ($filter || $srch): ?>
    <span style="font-size:13px;color:var(--text2);font-weight:400;margin-left:8px">
        — <?= htmlspecialchars($filter ?: '"'.$srch.'"') ?> filtresi
    </span>
    <?php endif; ?>
</div>

<!-- ============ ÖZET KPI ÇUBUĞU ============ -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-bottom:18px">

    <div class="kpi blue" style="padding:14px">
        <div class="kpi-label">Sipariş Tutarı</div>
        <div class="kpi-value" style="font-size:18px"><?= fmtTL($ozToplam) ?></div>
        <div class="kpi-sub"><?= number_format($totSip,0,',','.') ?> sipariş</div>
    </div>

    <div class="kpi red" style="padding:14px">
        <div class="kpi-label">Komisyon</div>
        <div class="kpi-value" style="font-size:18px"><?= fmtTL($ozKomisyon) ?></div>
        <div class="kpi-sub"><?= $ozToplam > 0 ? fmt($ozKomisyon/$ozToplam*100,1) : 0 ?>% oran</div>
    </div>

    <div class="kpi red" style="padding:14px">
        <div class="kpi-label">Kargo</div>
        <div class="kpi-value" style="font-size:18px"><?= fmtTL($ozKargo) ?></div>
        <div class="kpi-sub">Gönderim + iade</div>
    </div>

    <div class="kpi red" style="padding:14px">
        <div class="kpi-label">Platform Hizmet</div>
        <div class="kpi-value" style="font-size:18px"><?= fmtTL($ozPlatform) ?></div>
        <div class="kpi-sub">Trendyol kesintisi</div>
    </div>

    <div class="kpi" style="padding:14px">
        <div class="kpi-label">Net Tutar</div>
        <div class="kpi-value" style="font-size:18px"><?= fmtTL($ozNet) ?></div>
        <div class="kpi-sub">Kesintiler sonrası</div>
    </div>

    <?php if ($ozMaliyet > 0): ?>
    <div class="kpi red" style="padding:14px">
        <div class="kpi-label">Toplam Maliyet</div>
        <div class="kpi-value" style="font-size:18px"><?= fmtTL($ozMaliyet) ?></div>
        <div class="kpi-sub"><?= $ozMaliyetOlan ?>/<?= $totSip ?> sipariş</div>
    </div>

    <div class="kpi <?= $ozKar >= 0 ? 'green' : 'red' ?>" style="padding:14px">
        <div class="kpi-label"><?= $ozKar >= 0 ? 'Net Kar' : 'Net Zarar' ?></div>
        <div class="kpi-value <?= $ozKar >= 0 ? 'positive' : 'negative' ?>" style="font-size:18px"><?= fmtTL($ozKar) ?></div>
        <div class="kpi-sub">Marj: <?= $ozKarMarji ?>%</div>
    </div>
    <?php else: ?>
    <div class="kpi yellow" style="padding:14px">
        <div class="kpi-label">Kar Hesabı</div>
        <div class="kpi-value" style="font-size:15px;color:var(--yellow)">Maliyet Girin</div>
        <div class="kpi-sub">Kar/zarar için</div>
    </div>
    <?php endif; ?>

</div>
<!-- ========================================== -->

<div style="display:flex;gap:8px;margin-bottom:15px;flex-wrap:wrap;align-items:center">
    <a href="?action=siparisler" class="tab-btn <?= !$filter?'active':'' ?>">Tümü (<?= $stats['toplam_siparis'] ?>)</a>
    <?php foreach ($statusData as $s): ?>
    <a href="?action=siparisler&filter=<?= urlencode($s['siparis_statusu']) ?>" class="tab-btn <?= $filter===$s['siparis_statusu']?'active':'' ?>"><?= htmlspecialchars($s['siparis_statusu']) ?> (<?= $s['adet'] ?>)</a>
    <?php endforeach; ?>
    <div style="margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <a href="?action=siparisler&filter=<?= urlencode($filter) ?>&q=<?= urlencode($srch) ?>&sort=desc"
           class="tab-btn <?= $sort==='desc'?'active':'' ?>" style="padding:5px 10px;font-size:12px">↓ Yeniden Eskiye</a>
        <a href="?action=siparisler&filter=<?= urlencode($filter) ?>&q=<?= urlencode($srch) ?>&sort=asc"
           class="tab-btn <?= $sort==='asc'?'active':'' ?>" style="padding:5px 10px;font-size:12px">↑ Eskiden Yeniye</a>
        <form method="GET" style="display:flex;gap:6px"><input type="hidden" name="action" value="siparisler"><input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>"><input type="hidden" name="sort" value="<?= $sort ?>">
            <input type="text" name="q" value="<?= htmlspecialchars($srch) ?>" placeholder="Sipariş no, müşteri, ürün..." style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:8px;font-size:12px;outline:none;width:200px">
            <button type="submit" class="btn btn-sm btn-primary">🔍</button>
        </form>
        <span style="font-size:12px;color:var(--text2)"><span class="positive"><?= $eslesme ?> eşleşti</span> · <span style="color:var(--yellow)"><?= $stats['toplam_siparis']-$eslesme ?> eşleşmedi</span></span>
        <?php if ($apiOk): ?>
        <button class="btn btn-sm" style="background:var(--bg3);color:var(--text2)" onclick="document.getElementById('orderSyncPanel').classList.toggle('hidden')">📡 API'den Çek</button>
        <button class="btn btn-success btn-sm" onclick="rematch()">🔗 Eşleştir</button>
        <?php endif; ?>
    </div>
</div>
<?php if ($apiOk): ?>
<div id="orderSyncPanel" class="hidden" style="background:var(--card);border:1px solid var(--border);border-radius:10px;padding:15px 20px;margin-bottom:15px">
    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="margin:0">
            <label style="font-size:11px;color:var(--text2)">Başlangıç Tarihi</label>
            <input type="date" id="syncStart" value="<?= date('Y-m-d', strtotime('-30 days')) ?>" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:8px;font-size:13px">
        </div>
        <div class="form-group" style="margin:0">
            <label style="font-size:11px;color:var(--text2)">Bitiş Tarihi</label>
            <input type="date" id="syncEnd" value="<?= date('Y-m-d') ?>" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:8px;font-size:13px">
        </div>
        <button class="btn btn-primary" onclick="syncOrders()">📡 Sipariş Verilerini Çek</button>
        <span style="font-size:12px;color:var(--text2);align-self:center">
            <?php if ($stats['api_siparis_satirlari'] > 0): ?>
            <?= $stats['api_siparis_satirlari'] ?> sipariş için API verisi mevcut
            <?php if (isset($lastOrderSync)): ?> · Son güncelleme: <?= $lastOrderSync ?><?php endif; ?>
            <?php else: ?>API'den henüz sipariş çekilmemiş<?php endif; ?>
        </span>
    </div>
    <div style="margin-top:10px;font-size:12px;color:var(--text2)">
        ℹ️ API, siparişlerin <strong>ürün barkodunu</strong> içerir → eşleştirme çok daha doğru olur.
        Geniş aralıklar otomatik olarak 14 günlük dilimlere bölünerek çekilir, süre uzayabilir.
    </div>
</div>
<?php endif; ?>


<div class="card" style="padding:0;overflow:hidden">
<?php if (empty($orders)): ?>
<div class="no-data"><div class="icon">📭</div><p>Sipariş bulunamadı.</p></div>
<?php else: ?>
<?php if (isAdmin()): ?>
<div style="display:flex;align-items:center;gap:10px;padding:10px 16px;background:var(--bg3);border-bottom:1px solid var(--border)" id="bulkBar" style="display:none">
    <span id="seciliSayac" style="font-size:13px;color:var(--text2)">0 seçili</span>
    <button onclick="topluSil()" class="btn btn-danger" style="font-size:12px;padding:5px 14px" id="topluSilBtn" disabled>🗑 Seçilenleri Sil</button>
    <button onclick="tumunuSec(false)" style="background:none;border:none;color:var(--text2);font-size:12px;cursor:pointer">Seçimi Temizle</button>
</div>
<?php endif; ?>
<div style="overflow-x:auto"><table>
<thead><tr>
    <?php if (isAdmin()): ?><th style="width:36px;text-align:center"><input type="checkbox" id="chkAll" onchange="tumunuSec(this.checked)" style="cursor:pointer;width:15px;height:15px"></th><?php endif; ?>
    <th>Tarih</th><th>Sipariş No</th><th>Statü</th><th>Adet</th>
    <th>Ürün (API)</th>
    <th style="text-align:right">Satış Fiyatı</th>
    <th style="text-align:right">Sipariş Tutarı</th>
    <th style="text-align:right">Komisyon</th>
    <th style="text-align:right">Kargo</th>
    <th style="text-align:right">Platform</th>
    <th style="text-align:right">Ürün Maliyeti</th>
    <th style="text-align:right">Net Tutar</th>
    <th style="text-align:right">Sipariş Karı</th>
    <th style="text-align:center">Ödeme Durumu</th>
</tr></thead>
<tbody>
<?php foreach ($orders as $o):
    $birimFiyat = $o['urun_adedi']>0 ? $o['siparis_tutari']/$o['urun_adedi'] : 0;
    $birimM = $o['birim_maliyet']!==null ? ((float)$o['birim_maliyet']+(float)$o['kargo_maliyeti']+(float)$o['paket_maliyeti']+(float)$o['diger_maliyet']) : null;
    $toplamM = $birimM!==null ? $birimM*$o['urun_adedi'] : null;
    // Sadece İPTAL edilen siparişlerde kar/zarar hesabı yapılmaz
    // İade edilen siparişlerde net_tutar zaten negatif döner, o hesaplanmalı
    $iptalMi = (strpos($o['siparis_statusu']??'', 'İptal') !== false)
            || (strpos($o['siparis_statusu']??'', 'Cancelled') !== false)
            || (strpos($o['siparis_statusu']??'', 'Cancel') !== false);
    $kargo   = abs((float)$o['gonderi_kargo'])+abs((float)$o['iade_kargo']);
    $platform= abs((float)$o['platform_hizmet']);
    // net_tutar zaten komisyon+kargo+platform düşülmüş Trendyol net tutarıdır
    // Dolayısıyla: sipKar = net_tutar - ürün_maliyeti (kargo/platform ZATEN net_tutar'da yok)
    $sipKar  = (!$iptalMi && $toplamM!==null) ? $o['net_tutar'] - $toplamM : null;
    $s=$o['siparis_statusu'];
    $clsSrc = $o['api_statusu'] ?: $s;
    $cls=(strpos($clsSrc,'Teslim')!==false||$clsSrc==='Delivered'||$clsSrc==='AtCollectionPoint')?'badge-green':
         ((strpos($clsSrc,'İade')!==false||strpos($clsSrc,'İptal')!==false||$clsSrc==='UnDeliveredAndReturned'||$clsSrc==='Cancelled'||$clsSrc==='Returned')?'badge-red':
         ((strpos($clsSrc,'Yeni')!==false||$clsSrc==='Created')?'badge-blue':
         ($clsSrc==='UnPacked'?'badge-gray':'badge-orange')));
    $satirSayisi = (int)($o['satir_sayisi'] ?? 0);
    $cokluUrun   = $satirSayisi > 1;
?>
<tr data-id="<?= $o['id'] ?>">
    <?php if (isAdmin()): ?>
    <td style="text-align:center;padding:0 8px"><input type="checkbox" class="sipChk" value="<?= $o['id'] ?>" onchange="secimiGuncelle()" style="cursor:pointer;width:15px;height:15px"></td>
    <?php endif; ?>
    <td style="font-size:11px;color:var(--text2);white-space:nowrap"><?= htmlspecialchars(substr($o['siparis_tarihi']??'',0,16)) ?></td>
    <td style="font-family:monospace;font-size:11px"><?= htmlspecialchars($o['siparis_no']) ?></td>
    <td>
        <?php
        $statusTr = [
            'UnDeliveredAndReturned' => 'İade Edildi',
            'UnPacked'               => 'Hazırlanıyor',
            'AtCollectionPoint'      => 'Teslim Noktasında',
            'Shipped'                => 'Kargoya Verildi',
            'Delivered'              => 'Teslim Edildi',
            'Cancelled'              => 'İptal Edildi',
            'Created'                => 'Yeni Sipariş',
            'Picking'                => 'Hazırlanıyor',
            'Returned'               => 'İade Edildi',
            'ReadyToShip'            => 'Kargoya Hazır',
            'InTransit'              => 'Kargoda',
            'WaitingForSupply'       => 'Tedarik Bekleniyor',
            'Suspended'              => 'Askıya Alındı',
        ];
        $gosterilen = $o['api_statusu'] ?: $s;
        $gosterilen = $statusTr[$gosterilen] ?? $gosterilen;
        ?>
        <span class="badge <?= $cls ?>"><?= htmlspecialchars($gosterilen) ?></span>
    </td>
    <td style="text-align:center;font-weight:600"><?= fmt($o['urun_adedi'],0) ?></td>
    <td style="min-width:220px">
        <?php
        $satirSayisi  = (int)($o['satir_sayisi'] ?? 0);
        $apiGorseller = $o['api_gorseller'] ? array_values(array_filter(explode('|', $o['api_gorseller']))) : [];
        $apiBasliklar = $o['api_basliklar'] ? array_values(array_filter(explode('|', $o['api_basliklar']))) : [];
        ?>
        <?php if ($satirSayisi > 1 && !empty($apiBasliklar)): ?>
        <!-- Çoklu ürün -->
        <div style="display:flex;flex-direction:column;gap:4px">
            <?php
            $apiGorseller = array_values($apiGorseller);
            $apiBasliklar = array_values($apiBasliklar);
            foreach ($apiBasliklar as $bi => $baslik):
                $gorsel = $apiGorseller[$bi] ?? null;
            ?>
            <div style="display:flex;align-items:center;gap:6px">
                <?php if ($gorsel): ?>
                <img src="<?= htmlspecialchars($gorsel) ?>" style="width:26px;height:26px;object-fit:cover;border-radius:3px;flex-shrink:0" loading="lazy">
                <?php else: ?>
                <div style="width:26px;height:26px;background:var(--bg3);border-radius:3px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:12px">📦</div>
                <?php endif; ?>
                <span style="font-size:11px;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px" title="<?= htmlspecialchars($baslik) ?>"><?= htmlspecialchars(substr($baslik,0,35)) ?></span>
            </div>
            <?php endforeach; ?>
            <span style="font-size:10px;color:var(--blue);margin-top:1px">🛒 <?= $satirSayisi ?> farklı ürün</span>
        </div>
        <?php else: ?>
        <!-- Tekli ürün — standart görünüm -->
        <div style="display:flex;align-items:center;gap:8px">
            <?php if ($o['image_url']): ?>
            <img src="<?= htmlspecialchars($o['image_url']) ?>" style="width:32px;height:32px;object-fit:cover;border-radius:4px" loading="lazy">
            <?php elseif (!empty($apiGorseller[0])): ?>
            <img src="<?= htmlspecialchars($apiGorseller[0]) ?>" style="width:32px;height:32px;object-fit:cover;border-radius:4px" loading="lazy">
            <?php endif; ?>
            <div style="flex:1;min-width:0">
                <select class="urun-assign" style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:3px 6px;border-radius:6px;font-size:11px;max-width:180px;cursor:pointer"
                    onchange="assignOrder(this,'<?= htmlspecialchars($o['siparis_no'],ENT_QUOTES) ?>')">
                    <option value="">— Ürün Seç —</option>
                    <?php foreach ($tyDropdown as $td): ?>
                    <option value="<?= htmlspecialchars($td['ty_id'],ENT_QUOTES) ?>" <?= $o['ty_urun_id']===$td['ty_id']?'selected':'' ?>>
                        <?= htmlspecialchars(substr($td['title']??'',0,40)) ?> (<?= fmtTL($td['sale_price']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($apiBasliklar[0])): ?>
                <div style="font-size:10px;color:var(--green);margin-top:2px">✓ <?= htmlspecialchars(substr($apiBasliklar[0],0,35)) ?></div>
                <?php elseif ($o['ty_urun_id']): ?>
                <div style="font-size:10px;color:var(--green);margin-top:2px">✓ <?= htmlspecialchars(substr($o['ty_title']??'',0,35)) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </td>
    <td style="text-align:right;font-size:12px"><?= fmtTL($birimFiyat) ?></td>
    <td style="text-align:right;font-weight:600"><?= fmtTL($o['siparis_tutari']) ?></td>
    <td style="text-align:right" class="negative"><?= fmtTL(abs($o['komisyon'])) ?></td>
    <td style="text-align:right"><?= $kargo>0 ? '<span class="negative">'.fmtTL($kargo).'</span>' : '<span class="neutral">—</span>' ?></td>
    <td style="text-align:right"><?= $platform>0 ? '<span class="negative">'.fmtTL($platform).'</span>' : '<span class="neutral">—</span>' ?></td>
    <td style="text-align:right">
    <?php if ($birimM!==null): ?>
        <div class="tip"><span class="cost-chip has"><?= fmtTL($toplamM) ?></span>
        <div class="tip-box">
            Birim: <?= fmtTL($birimM) ?><br>
            × <?= (int)$o['urun_adedi'] ?> adet<br>
            ──────<br>
            Toplam: <?= fmtTL($toplamM) ?><br>
            <small style="color:#9099c4">(Ürün: <?= fmtTL($o['birim_maliyet']) ?>, Kargo: <?= fmtTL($o['kargo_maliyeti']) ?>, Paket: <?= fmtTL($o['paket_maliyeti']) ?>)</small>
        </div></div>
    <?php else: ?><span class="cost-chip no">—</span><?php endif; ?>
    </td>
    <td style="text-align:right;font-weight:600"><?= fmtTL($o['net_tutar']) ?></td>
    <td style="text-align:right"><?= $sipKar!==null ? '<span class="'.($sipKar>=0?'positive':'negative').'">'.fmtTL($sipKar).'</span>' : '<span class="neutral">—</span>' ?></td>
    <td style="text-align:center">
    <?php
    $odVade  = $o['od_vade_tarihi'] ? substr($o['od_vade_tarihi'],0,10) : null;
    $odTipler= $o['od_islem_tipi'] ?? null;
    $today2  = date('Y-m-d');
    if (!$odTipler) {
        echo '<span class="badge badge-gray" title="Ödeme Detay yüklenmemiş veya eşleşmedi">—</span>';
    } elseif (strpos($odTipler,'İade') !== false && strpos($odTipler,'Satış') === false) {
        echo '<span class="badge badge-red">↩ İade</span>';
    } elseif ($odVade && $odVade <= $today2) {
        echo '<span class="badge badge-green" title="Vade: '.$odVade.'">✅ Ödendi</span>';
        echo '<div style="font-size:10px;color:var(--text2);margin-top:2px">'.$odVade.'</div>';
    } elseif ($odVade) {
        echo '<span class="badge badge-yellow" title="Vade: '.$odVade.'">⏳ Bekliyor</span>';
        echo '<div style="font-size:10px;color:var(--yellow);margin-top:2px">'.$odVade.'</div>';
    } else {
        echo '<span class="badge badge-gray">—</span>';
    }
    ?>
    </td>

</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if ($pages>1): ?>
<div style="padding:15px 20px;display:flex;gap:6px;flex-wrap:wrap">
    <?php for ($i=1;$i<=$pages;$i++): ?><a href="?action=siparisler&p=<?= $i ?>&filter=<?= urlencode($filter) ?>&q=<?= urlencode($srch) ?>&sort=<?= $sort ?>" class="tab-btn <?= $i===$pg?'active':'' ?>" style="padding:5px 10px;font-size:12px"><?= $i ?></a><?php endfor; ?>
</div><?php endif; ?>
<?php endif; ?>
</div>

<?php elseif ($action === 'urunler'): ?>
<?php
$sortOpts = ['net_ciro'=>'Net Ciro','net_satis'=>'Net Satış','komisyon'=>'Komisyon','siparis_sayisi'=>'Sipariş'];
$sortBy   = in_array($_GET['sort']??'', array_keys($sortOpts)) ? $_GET['sort'] : 'net_ciro';
$urunler  = DB::rows("
    SELECT tu.ty_id, tu.title, tu.image_url, tu.barcode, tu.category_name,
           tu.sale_price, tu.quantity as guncel_stok,
           COUNT(DISTINCT s.id)                AS siparis_sayisi,
           SUM(s.urun_adedi)                   AS net_satis,
           SUM(s.siparis_tutari)               AS net_ciro,
           SUM(ABS(s.komisyon))                AS komisyon,
           SUM(ABS(s.gonderi_kargo)+ABS(s.iade_kargo)) AS kargo,
           SUM(ABS(s.platform_hizmet))         AS platform,
           SUM(s.net_tutar)                    AS net_tutar_toplam,
           SUM(s.siparis_tutari) - SUM(ABS(s.komisyon)) - SUM(ABS(s.gonderi_kargo)+ABS(s.iade_kargo)) - SUM(ABS(s.platform_hizmet)) AS net_hesapli,
           m.birim_maliyet, m.kargo_maliyeti, m.paket_maliyeti, m.diger_maliyet,
           (m.birim_maliyet+m.kargo_maliyeti+m.paket_maliyeti+m.diger_maliyet) AS birim_toplam,
           SUM(s.urun_adedi)*(m.birim_maliyet+m.kargo_maliyeti+m.paket_maliyeti+m.diger_maliyet) AS toplam_maliyet,
           CASE WHEN m.birim_maliyet IS NOT NULL THEN
               (SUM(s.siparis_tutari) - SUM(ABS(s.komisyon)) - SUM(ABS(s.gonderi_kargo)+ABS(s.iade_kargo)) - SUM(ABS(s.platform_hizmet)))
               - SUM(s.urun_adedi)*(m.birim_maliyet+m.kargo_maliyeti+m.paket_maliyeti+m.diger_maliyet)
           ELSE NULL END AS kar,
           CASE WHEN m.birim_maliyet IS NOT NULL AND SUM(s.siparis_tutari)>0 THEN
               ROUND(((SUM(s.siparis_tutari) - SUM(ABS(s.komisyon)) - SUM(ABS(s.gonderi_kargo)+ABS(s.iade_kargo)) - SUM(ABS(s.platform_hizmet)))
               - SUM(s.urun_adedi)*(m.birim_maliyet+m.kargo_maliyeti+m.paket_maliyeti+m.diger_maliyet))
                   / SUM(s.siparis_tutari)*100, 1)
           ELSE NULL END AS kar_marji
    FROM trendyol_urunler tu
    JOIN siparisler s ON s.ty_urun_id = tu.ty_id AND s.magaza_id = tu.magaza_id
    LEFT JOIN maliyetler m ON m.ty_urun_id = tu.ty_id AND m.magaza_id = tu.magaza_id
    WHERE tu.magaza_id = ?
      AND s.siparis_statusu NOT LIKE '%İptal%'
      AND s.siparis_statusu NOT LIKE '%Cancel%'
    GROUP BY tu.ty_id, tu.title, tu.image_url, tu.barcode, tu.category_name,
             tu.sale_price, tu.quantity,
             m.birim_maliyet, m.kargo_maliyeti, m.paket_maliyeti, m.diger_maliyet
    ORDER BY $sortBy DESC
", [$magazaId]);
$maliyetsizSayisi = count(array_filter($urunler, fn($r) => $r['birim_maliyet'] === null));
?>
<div class="page-title">🏷️ <span>Ürün Analizi</span>
    <span style="font-size:13px;color:var(--text2);font-weight:400;margin-left:8px">Sipariş verisinden</span>
</div>
<div style="display:flex;gap:8px;margin-bottom:15px;flex-wrap:wrap;align-items:center">
    <span style="color:var(--text2);font-size:12px;align-self:center">Sırala:</span>
    <?php foreach ($sortOpts as $k=>$v): ?>
    <a href="?action=urunler&sort=<?= $k ?>" class="tab-btn <?= $sortBy===$k?'active':'' ?>"><?= $v ?></a>
    <?php endforeach; ?>
    <span style="margin-left:auto;color:var(--text2);font-size:12px">
        <?= count($urunler) ?> ürün
        <?php if ($maliyetsizSayisi>0): ?> · <a href="?action=ty_urunler&maliyet=yok" style="color:var(--yellow);text-decoration:none">⚠️ <?= $maliyetsizSayisi ?> maliyetsiz</a><?php endif; ?>
    </span>
</div>
<div class="card" style="padding:0;overflow:hidden">
<?php if (empty($urunler)): ?>
<div class="no-data"><div class="icon">🏷️</div><p>Henüz sipariş verisi yok. API'den sipariş çekin veya Excel yükleyin.</p></div>
<?php else: ?>
<div style="overflow-x:auto"><table>
<thead><tr>
    <th>Görsel</th><th>Ürün Adı</th><th>Kategori</th>
    <th style="text-align:right">Sipariş</th>
    <th style="text-align:right">Adet</th>
    <th style="text-align:right">Brüt Ciro</th>
    <th style="text-align:right">Komisyon</th>
    <th style="text-align:right">Kargo</th>
    <th style="text-align:right">Platform</th>
    <th style="text-align:right">Net Tutar</th>
    <th style="text-align:right">Birim Maliyet</th>
    <th style="text-align:right">Toplam Maliyet</th>
    <th style="text-align:right">Stok</th>
    <th style="text-align:right">Kar</th>
    <th style="text-align:right">Marj</th>
</tr></thead>
<tbody>
<?php foreach ($urunler as $u):
    $hm = $u['birim_maliyet'] !== null;
    $bT = $hm ? (float)$u['birim_toplam'] : null;
    $tT = $hm ? (float)$u['toplam_maliyet'] : null;
    $kr = $hm ? (float)$u['kar'] : null;
    $mj = $hm ? (float)$u['kar_marji'] : null;
?>
<tr>
    <td><?php if ($u['image_url']): ?><img src="<?= htmlspecialchars($u['image_url']) ?>" class="product-img" loading="lazy"><?php else: ?><div class="product-img" style="display:flex;align-items:center;justify-content:center;font-size:16px;background:var(--bg3)">📦</div><?php endif; ?></td>
    <td style="max-width:180px">
        <div style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($u['title']) ?></div>
        <div style="font-size:11px;color:var(--text2);font-family:monospace"><?= htmlspecialchars($u['barcode']) ?></div>
        <span style="font-size:10px;color:var(--green)">✓ API</span>
    </td>
    <td><span class="badge badge-blue" style="font-size:10px"><?= htmlspecialchars($u['category_name']??'-') ?></span></td>
    <td style="text-align:right;font-weight:600"><?= fmt($u['siparis_sayisi'],0) ?></td>
    <td style="text-align:right"><?= fmt($u['net_satis'],0) ?></td>
    <td style="text-align:right;font-weight:600"><?= fmtTL($u['net_ciro']) ?></td>
    <td style="text-align:right" class="negative"><?= fmtTL($u['komisyon']) ?></td>
    <td style="text-align:right"><?= (float)$u['kargo']>0 ? '<span class="negative">'.fmtTL($u['kargo']).'</span>' : '<span class="neutral">—</span>' ?></td>
    <td style="text-align:right"><?= (float)$u['platform']>0 ? '<span class="negative">'.fmtTL($u['platform']).'</span>' : '<span class="neutral">—</span>' ?></td>
    <?php
    $netHesap = (float)$u['net_hesapli'];
    $netDb    = (float)$u['net_tutar_toplam'];
    $netGoster= $netHesap !== 0.0 ? $netHesap : $netDb;
    ?>
    <td style="text-align:right;font-weight:600">
        <span><?= fmtTL($netGoster) ?></span>
        <?php if ($netDb > 0 && abs($netHesap - $netDb) > 1): ?>
        <div class="tip" style="display:inline">
            <span style="font-size:9px;color:var(--text2);cursor:help;margin-left:2px">ℹ</span>
            <div class="tip-box">Hesaplanan: <?= fmtTL($netHesap) ?><br>Excel'den: <?= fmtTL($netDb) ?><br><small>Fark: indirim/ceza/iade</small></div>
        </div>
        <?php endif; ?>
    </td>
    <td style="text-align:right">
        <?php if ($hm): ?>
        <div class="tip"><span class="cost-chip has"><?= fmtTL($bT) ?></span>
        <div class="tip-box">Ürün: <?= fmtTL($u['birim_maliyet']) ?><br>Kargo: <?= fmtTL($u['kargo_maliyeti']) ?><br>Paket: <?= fmtTL($u['paket_maliyeti']) ?></div></div>
        <a href="#" onclick="openCostModal('<?= htmlspecialchars($u['ty_id'],ENT_QUOTES) ?>','<?= htmlspecialchars($u['barcode']??'',ENT_QUOTES) ?>','<?= htmlspecialchars(addslashes($u['title'])) ?>');return false" style="font-size:10px;color:var(--text2);margin-left:4px;text-decoration:none">✏️</a>
        <?php else: ?>
        <a href="#" onclick="openCostModal('<?= htmlspecialchars($u['ty_id']??'',ENT_QUOTES) ?>','<?= htmlspecialchars($u['barcode']??'',ENT_QUOTES) ?>','<?= htmlspecialchars(addslashes($u['title'])) ?>');return false" class="btn btn-sm" style="background:rgba(241,196,15,.1);color:var(--yellow);border:1px solid rgba(241,196,15,.2);font-size:10px">+ Maliyet</a>
        <?php endif; ?>
    </td>
    <td style="text-align:right"><?= $tT!==null ? '<span class="negative">'.fmtTL($tT).'</span>' : '<span class="neutral">—</span>' ?></td>
    <td style="text-align:right"><?= fmt($u['guncel_stok'],0) ?></td>
    <td style="text-align:right;font-weight:600"><?php if ($kr!==null): ?><span class="<?= $kr>=0?'positive':'negative' ?>"><?= fmtTL($kr) ?></span><?php else: ?><span class="neutral">—</span><?php endif; ?></td>
    <td style="text-align:right"><?php if ($mj!==null): ?><span class="badge <?= $mj>=30?'badge-green':($mj>=10?'badge-yellow':($mj>=0?'badge-orange':'badge-red')) ?>"><?= $mj ?>%</span><?php else: ?><span class="neutral">—</span><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</div>

<?php elseif ($action === 'kar_zarar'): ?>
<?php
$karAnaliz = DB::rows("
    SELECT tu.ty_id, tu.title, tu.image_url, tu.barcode, tu.category_name,
           COUNT(DISTINCT s.id)               AS siparis_sayisi,
           SUM(s.urun_adedi)                  AS net_satis,
           SUM(s.siparis_tutari)              AS brut_ciro,
           SUM(ABS(s.komisyon))               AS toplam_komisyon,
           SUM(ABS(s.gonderi_kargo)+ABS(s.iade_kargo)) AS toplam_kargo,
           SUM(ABS(s.platform_hizmet))        AS toplam_platform,
           SUM(s.net_tutar)                   AS net_tutar_db,
           SUM(s.siparis_tutari) - SUM(ABS(s.komisyon)) - SUM(ABS(s.gonderi_kargo)+ABS(s.iade_kargo)) - SUM(ABS(s.platform_hizmet)) AS net_tutar,
           m.birim_maliyet, m.kargo_maliyeti, m.paket_maliyeti, m.diger_maliyet,
           (m.birim_maliyet+m.kargo_maliyeti+m.paket_maliyeti+m.diger_maliyet) AS birim_toplam,
           SUM(s.urun_adedi)*(m.birim_maliyet+m.kargo_maliyeti+m.paket_maliyeti+m.diger_maliyet) AS toplam_maliyet,
           (SUM(s.siparis_tutari) - SUM(ABS(s.komisyon)) - SUM(ABS(s.gonderi_kargo)+ABS(s.iade_kargo)) - SUM(ABS(s.platform_hizmet)))
               - SUM(s.urun_adedi)*(m.birim_maliyet+m.kargo_maliyeti+m.paket_maliyeti+m.diger_maliyet) AS kar,
           CASE WHEN SUM(s.siparis_tutari)>0 THEN
               ROUND(((SUM(s.siparis_tutari) - SUM(ABS(s.komisyon)) - SUM(ABS(s.gonderi_kargo)+ABS(s.iade_kargo)) - SUM(ABS(s.platform_hizmet)))
               - SUM(s.urun_adedi)*(m.birim_maliyet+m.kargo_maliyeti+m.paket_maliyeti+m.diger_maliyet))
                   / SUM(s.siparis_tutari)*100, 1)
           ELSE 0 END AS kar_marji
    FROM trendyol_urunler tu
    JOIN siparisler s ON s.ty_urun_id = tu.ty_id AND s.magaza_id = tu.magaza_id
    JOIN maliyetler m ON m.ty_urun_id = tu.ty_id AND m.magaza_id = tu.magaza_id
    WHERE tu.magaza_id = ?
      AND s.siparis_statusu NOT LIKE '%İptal%'
      AND s.siparis_statusu NOT LIKE '%Cancel%'
    GROUP BY tu.ty_id, tu.title, tu.image_url, tu.barcode, tu.category_name,
             m.birim_maliyet, m.kargo_maliyeti, m.paket_maliyeti, m.diger_maliyet
    ORDER BY kar DESC
", [$magazaId]);

$tBrutCiro = array_sum(array_column($karAnaliz,'brut_ciro'));
$tNetTutar = array_sum(array_column($karAnaliz,'net_tutar'));
$tKomisyon = array_sum(array_column($karAnaliz,'toplam_komisyon'));
$tKargo    = array_sum(array_column($karAnaliz,'toplam_kargo'));
$tPlatform = array_sum(array_column($karAnaliz,'toplam_platform'));
$tMaliyet  = array_sum(array_column($karAnaliz,'toplam_maliyet'));
$tKar      = array_sum(array_column($karAnaliz,'kar'));
$genelMarj = $tBrutCiro>0 ? ($tKar/$tBrutCiro)*100 : 0;
$karli     = array_filter($karAnaliz, fn($r)=>$r['kar']>=0);
$zararli   = array_filter($karAnaliz, fn($r)=>$r['kar']<0);
$maliyetsiz = (int)DB::scalar("
    SELECT COUNT(DISTINCT tu.ty_id) FROM trendyol_urunler tu
    JOIN siparisler s ON s.ty_urun_id=tu.ty_id AND s.magaza_id=tu.magaza_id
    LEFT JOIN maliyetler m ON m.ty_urun_id=tu.ty_id AND m.magaza_id=tu.magaza_id
    WHERE tu.magaza_id=? AND m.ty_urun_id IS NULL", [$magazaId]);
?>
<div class="page-title">📈 <span>Kar/Zarar</span> Detaylı Analiz</div>
<?php if ($maliyetsiz>0): ?>
<div class="alert alert-warning">⚠️ <strong><?= $maliyetsiz ?> ürünün</strong> maliyeti girilmemiş — hesaba dahil edilmedi.
    <a href="?action=ty_urunler&maliyet=yok" style="color:var(--yellow)">Maliyet gir →</a></div>
<?php endif; ?>
<?php if (empty($karAnaliz)): ?>
<div class="card"><div class="no-data"><div class="icon">⚠️</div>
    <p style="font-size:15px;margin-bottom:8px">Henüz maliyet girilmemiş</p>
    <p style="color:var(--text2)"><a href="?action=ty_urunler" style="color:var(--primary)">Trendyol Ürünleri</a> sayfasından ürünlerinize maliyet girin.</p>
</div></div>
<?php else: ?>
<div class="kpi-grid">
    <div class="kpi blue"><div class="kpi-label">Brüt Ciro</div><div class="kpi-value"><?= fmtTL($tBrutCiro) ?></div><div class="kpi-sub">Sipariş tutarı</div></div>
    <div class="kpi red"><div class="kpi-label">Komisyon</div><div class="kpi-value"><?= fmtTL($tKomisyon) ?></div><div class="kpi-sub"><?= $tBrutCiro>0?fmt($tKomisyon/$tBrutCiro*100,1):0 ?>% oran</div></div>
    <div class="kpi red"><div class="kpi-label">Kargo</div><div class="kpi-value"><?= fmtTL($tKargo) ?></div><div class="kpi-sub">Gönderim + iade</div></div>
    <div class="kpi red"><div class="kpi-label">Platform Hizmet</div><div class="kpi-value"><?= fmtTL($tPlatform) ?></div><div class="kpi-sub">Trendyol kesintisi</div></div>
    <div class="kpi"><div class="kpi-label">Net Tutar</div><div class="kpi-value"><?= fmtTL($tNetTutar) ?></div><div class="kpi-sub">Tüm kesintiler sonrası</div></div>
    <div class="kpi red"><div class="kpi-label">Ürün Maliyeti</div><div class="kpi-value"><?= fmtTL($tMaliyet) ?></div><div class="kpi-sub">Toplam gider</div></div>
    <div class="kpi <?= $tKar>=0?'green':'red' ?>">
        <div class="kpi-label"><?= $tKar>=0?'Net Kar':'Net Zarar' ?></div>
        <div class="kpi-value <?= $tKar>=0?'positive':'negative' ?>"><?= fmtTL($tKar) ?></div>
        <div class="kpi-sub">Marj: <?= fmt($genelMarj,1) ?>%</div>
    </div>
    <div class="kpi"><div class="kpi-label">Karlı / Zararlı</div>
        <div class="kpi-value"><span class="positive"><?= count($karli) ?></span> / <span class="negative"><?= count($zararli) ?></span></div>
        <div class="kpi-sub"><?= count($karAnaliz) ?> ürün</div>
    </div>
</div>
<div class="grid-2">
    <div class="card"><div class="card-title">📊 Ürün Kar/Zarar Grafiği</div><div class="chart-wrap"><canvas id="karChart"></canvas></div></div>
    <div class="card"><div class="card-title">🏆 En Karlı Ürünler</div>
    <?php $top5=array_slice($karAnaliz,0,5); $mxK=$top5?max(array_column($top5,'kar')):1; ?>
    <?php foreach ($top5 as $u): ?>
    <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;margin-bottom:3px;gap:5px">
            <span style="font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars(substr($u['title'],0,35)) ?></span>
            <span class="<?= $u['kar']>=0?'positive':'negative' ?>" style="font-size:12px;white-space:nowrap"><?= fmtTL($u['kar']) ?></span>
        </div>
        <div class="progress-bar"><div class="progress-fill <?= $u['kar']>=0?'green':'red' ?>" style="width:<?= $mxK>0?min(100,abs($u['kar'])/$mxK*100):0 ?>%"></div></div>
    </div><?php endforeach; ?>
    </div>
</div>
<div class="card" style="padding:0;overflow:hidden">
    <div style="padding:15px 20px;border-bottom:1px solid var(--border)"><div class="card-title" style="margin:0">📋 Ürün Bazlı Kar/Zarar</div></div>
    <div style="overflow-x:auto"><table>
    <thead><tr>
        <th>Görsel</th><th>Ürün</th><th>Kategori</th>
        <th style="text-align:right">Sipariş</th>
        <th style="text-align:right">Brüt Ciro</th>
        <th style="text-align:right">Komisyon</th>
        <th style="text-align:right">Kargo</th>
        <th style="text-align:right">Platform</th>
        <th style="text-align:right">Net Tutar</th>
        <th style="text-align:right">Birim M.</th>
        <th style="text-align:right">Toplam M.</th>
        <th style="text-align:right">Kar/Zarar</th>
        <th style="text-align:right">Marj</th>
    </tr></thead><tbody>
    <?php foreach ($karAnaliz as $u): ?>
    <tr>
        <td><?php if ($u['image_url']): ?><img src="<?= htmlspecialchars($u['image_url']) ?>" class="product-img" loading="lazy"><?php else: ?><div class="product-img" style="display:flex;align-items:center;justify-content:center;background:var(--bg3)">📦</div><?php endif; ?></td>
        <td><div style="font-weight:500;font-size:13px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($u['title']) ?></div>
            <div style="font-size:11px;color:var(--text2);font-family:monospace"><?= htmlspecialchars($u['barcode']) ?></div></td>
        <td><span class="badge badge-blue" style="font-size:10px"><?= htmlspecialchars($u['category_name']??'-') ?></span></td>
        <td style="text-align:right"><?= fmt($u['siparis_sayisi'],0) ?></td>
        <td style="text-align:right"><?= fmtTL($u['brut_ciro']) ?></td>
        <td style="text-align:right" class="negative"><?= fmtTL($u['toplam_komisyon']) ?></td>
        <td style="text-align:right"><?= (float)$u['toplam_kargo']>0 ? '<span class="negative">'.fmtTL($u['toplam_kargo']).'</span>' : '<span class="neutral">—</span>' ?></td>
        <td style="text-align:right"><?= (float)$u['toplam_platform']>0 ? '<span class="negative">'.fmtTL($u['toplam_platform']).'</span>' : '<span class="neutral">—</span>' ?></td>
        <td style="text-align:right;font-weight:500">
            <?= fmtTL((float)$u['net_tutar']) ?>
            <?php $diff=abs((float)$u['net_tutar']-(float)$u['net_tutar_db']); if ($diff>1 && (float)$u['net_tutar_db']>0): ?>
            <div class="tip" style="display:inline"><span style="font-size:9px;color:var(--text2);cursor:help;margin-left:2px">ℹ</span>
            <div class="tip-box">Hesaplanan: <?= fmtTL($u['net_tutar']) ?><br>Excel'den: <?= fmtTL($u['net_tutar_db']) ?><br><small>Fark: indirim/ceza/iade</small></div></div>
            <?php endif; ?>
        </td>
        <td style="text-align:right" class="negative"><?= fmtTL($u['birim_toplam']) ?></td>
        <td style="text-align:right" class="negative"><?= fmtTL($u['toplam_maliyet']) ?></td>
        <td style="text-align:right"><span class="<?= $u['kar']>=0?'positive':'negative' ?>"><?= fmtTL($u['kar']) ?></span></td>
        <td style="text-align:right"><span class="badge <?= $u['kar_marji']>=20?'badge-green':($u['kar_marji']>=0?'badge-yellow':'badge-red') ?>"><?= fmt($u['kar_marji'],1) ?>%</span></td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<script>
new Chart(document.getElementById('karChart'),{type:'bar',data:{
    labels:<?= json_encode(array_map(fn($r)=>substr($r['title'],0,22),array_slice($karAnaliz,0,10))) ?>,
    datasets:[{label:'Kar/Zarar (₺)',
        data:<?= json_encode(array_map(fn($r)=>round((float)$r['kar'],2),array_slice($karAnaliz,0,10))) ?>,
        backgroundColor:<?= json_encode(array_map(fn($r)=>$r['kar']>=0?'rgba(46,204,113,.7)':'rgba(231,76,60,.7)',array_slice($karAnaliz,0,10))) ?>,
        borderWidth:1}]},
    options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false}},
        scales:{x:{ticks:{color:'#9099c4',font:{size:10},callback:v=>v.toLocaleString('tr')+'₺'},grid:{color:'#2e3150'}},
                y:{ticks:{color:'#9099c4',font:{size:10}},grid:{color:'#2e3150'}}}}});
</script>
<?php endif; ?>

<?php elseif ($action === 'maliyetler'): ?>
<?php
$maliyetler = DB::rows("SELECT m.*, tu.title, tu.image_url, tu.sale_price,
    u.net_satis, u.net_ciro, u.toplam_komisyon,
    u.net_ciro - u.toplam_komisyon - ((m.birim_maliyet+m.kargo_maliyeti+m.paket_maliyeti+m.diger_maliyet)*u.net_satis) as kar
    FROM maliyetler m
    LEFT JOIN trendyol_urunler tu ON m.ty_urun_id = tu.ty_id AND tu.magaza_id = m.magaza_id
    LEFT JOIN urun_satis u ON u.ty_urun_id = m.ty_urun_id AND u.magaza_id = m.magaza_id
    WHERE m.magaza_id=?
    ORDER BY m.urun_adi", [$magazaId]);
$tyListForCost = DB::rows("SELECT ty_id, barcode, title, sale_price FROM trendyol_urunler WHERE magaza_id=? ORDER BY title LIMIT 2000", [$magazaId]);
?>
<div class="page-title">💰 <span>Maliyet Yönetimi</span></div>
<div class="grid-2">
<div class="card">
    <div class="card-title">➕ Maliyet Ekle / Güncelle</div>
    <div class="form-grid">
        <div class="form-group" style="grid-column:1/-1">
            <label>Trendyol Ürünü Seç</label>
            <select id="cm_select" onchange="selectCostProduct(this)">
                <option value="">— API Ürünü Seçin —</option>
                <?php foreach ($tyListForCost as $p): ?>
                <option value="<?= htmlspecialchars($p['ty_id'],ENT_QUOTES) ?>"
                    data-barcode="<?= htmlspecialchars($p['barcode']??'',ENT_QUOTES) ?>"
                    data-title="<?= htmlspecialchars(addslashes($p['title']??'')) ?>"
                    data-price="<?= $p['sale_price'] ?>">
                    <?= htmlspecialchars(substr($p['title']??'',0,60)) ?> — <?= fmtTL($p['sale_price']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label>Seçilen Ürün</label><input type="text" id="cm_title" readonly style="color:var(--text2)"></div>
        <div class="form-group"><label>Birim Maliyet ₺ *</label><input type="number" id="cm_birim" step="0.01" min="0" placeholder="0.00"></div>
        <div class="form-group"><label>Kargo Maliyeti ₺</label><input type="number" id="cm_kargo" step="0.01" min="0" value="0"></div>
        <div class="form-group"><label>Paketleme ₺</label><input type="number" id="cm_paket" step="0.01" min="0" value="0"></div>
        <div class="form-group"><label>Diğer ₺</label><input type="number" id="cm_diger" step="0.01" min="0" value="0"></div>
    </div>
    <br><button class="btn btn-primary" onclick="saveCostFromForm()">💾 Kaydet</button>
    <?php if (empty($tyListForCost)): ?>
    <div class="alert alert-warning" style="margin-top:10px">Önce <a href="?action=ty_urunler" style="color:var(--primary)">Trendyol Ürünleri</a> sayfasından API senkronizasyonu yapın.</div>
    <?php endif; ?>
</div>
<div class="card">
    <div class="card-title">📋 Kayıtlı Maliyetler (<?= count($maliyetler) ?>)</div>
    <?php if (empty($maliyetler)): ?>
    <div class="no-data"><div class="icon">💸</div><p>Henüz maliyet girilmemiş.</p></div>
    <?php else: ?>
    <div style="overflow-x:auto"><table>
    <thead><tr><th>Ürün</th><th style="text-align:right">Birim</th><th style="text-align:right">Net Ciro</th><th style="text-align:right">Kar</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($maliyetler as $m): $t=(float)$m['birim_maliyet']+(float)$m['kargo_maliyeti']+(float)$m['paket_maliyeti']+(float)$m['diger_maliyet']; ?>
    <tr>
        <td style="max-width:200px">
            <div style="font-weight:500;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($m['title']?:$m['urun_adi']) ?></div>
            <div style="font-size:10px;color:var(--text2);font-family:monospace"><?= htmlspecialchars($m['barcode']) ?></div>
        </td>
        <td style="text-align:right;font-size:12px"><?= fmtTL($t) ?></td>
        <td style="text-align:right;font-size:12px"><?= $m['net_ciro'] ? fmtTL($m['net_ciro']) : '—' ?></td>
        <td style="text-align:right"><?= $m['kar']!==null ? "<span class='".($m['kar']>=0?'positive':'negative')."'>".fmtTL($m['kar'])."</span>" : '<span class="neutral">—</span>' ?></td>
        <td><a href="#" onclick="deleteCost(<?= $m['id'] ?>,this);return false" class="btn btn-danger btn-sm">🗑</a></td>
    </tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</div></div>

<?php elseif ($action === 'veri_yukle'): ?>
<?php
// Reklam Excel yükleme
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['file_type']??'')==='reklamlar') {
    $file = $_FILES['excel_file'] ?? null;
    if ($file && $file['error']=== 0) {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $ins = 0;
            $now = date('Y-m-d H:i:s');
            $parseNum = function($v) {
                $s = trim((string)$v);
                if ($s==='' || $s==='-') return 0.0;
                $s = preg_replace('/[^0-9,.]/', '', $s);
                if (strpos($s,'.')!==false && strpos($s,',')!==false) {
                    $s = str_replace('.', '', $s);
                    $s = str_replace(',', '.', $s);
                } elseif (strpos($s,',')!==false) {
                    $s = str_replace(',', '.', $s);
                }
                return (float)$s;
            };
            DB::exec("DELETE FROM reklamlar WHERE magaza_id=?", [$magazaId]);
            foreach ($rows as $i => $r) {
                if ($i===0 || empty($r[0])) continue;
                DB::exec(
                    "INSERT INTO reklamlar
                     (magaza_id,reklam_adi,statu,baslangic_tarihi,bitis_tarihi,urun_adedi,
                      toplam_butce,kalan_butce,harcama,gerceklesen_tbm,tiklanma,goruntulenme,
                      dogrudan_satis,dolayli_satis,toplam_satis,
                      dogrudan_ciro,dolayli_ciro,toplam_ciro,roas,yukleme_tarihi)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    [
                        $magazaId,
                        (string)($r[0] ?? ''),
                        (string)($r[1] ?? ''),
                        (string)($r[2] ?? ''),
                        (string)($r[3] ?? ''),
                        (int)($r[4] ?? 0),
                        $parseNum($r[6] ?? 0),
                        $parseNum($r[8] ?? 0),
                        $parseNum($r[9] ?? 0),
                        $parseNum($r[11] ?? 0),
                        (int)($r[12] ?? 0),
                        (int)($r[13] ?? 0),
                        (int)($r[14] ?? 0),
                        (int)($r[15] ?? 0),
                        (int)($r[16] ?? 0),
                        $parseNum($r[17] ?? 0),
                        $parseNum($r[18] ?? 0),
                        $parseNum($r[19] ?? 0),
                        $parseNum($r[20] ?? 0),
                        $now,
                    ]
                );
                $ins++;
            }
            $message = "✅ $ins reklam yüklendi";
        } catch (Exception $e) {
            $error = "Reklam yükleme hatası: " . $e->getMessage();
        }
    } elseif ($file) {
        $error = "Dosya yükleme hatası: " . ($file['error'] ?? '?');
    }
}
$reklamSayisi = 0;
try { $reklamSayisi = (int)DB::scalar("SELECT COUNT(*) FROM reklamlar WHERE magaza_id=?",[$magazaId]); } catch(PDOException $e) {}
$komSayisi = 0;
try { $komSayisi = (int)DB::scalar("SELECT COUNT(*) FROM komisyon_tarifeleri WHERE magaza_id=?",[$magazaId]); } catch(PDOException $e) {}
?>
<div class="page-title">📤 <span>Veri Yükle</span></div>

<!-- Özet İstatistikler -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px">
    <div class="kpi">
        <div class="kpi-label">Sipariş Kayıtları</div>
        <div class="kpi-value"><?= number_format($stats['toplam_siparis'],0,',','.') ?></div>
        <div class="kpi-sub"><?= fmtTL($stats['toplam_siparis_tutari']) ?> ciro</div>
    </div>
    <div class="kpi">
        <div class="kpi-label">Reklam Kampanyası</div>
        <div class="kpi-value"><?= $reklamSayisi ?></div>
        <div class="kpi-sub">Kayıtlı kampanya</div>
    </div>
    <div class="kpi">
        <div class="kpi-label">Komisyon Tarifesi</div>
        <div class="kpi-value"><?= $komSayisi ?></div>
        <div class="kpi-sub">Ürün tarifesi</div>
    </div>
</div>

<!-- Yükleme Kartları -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">

<!-- Sipariş Kayıtları -->
<div class="card" style="display:flex;flex-direction:column">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
        <span style="font-size:24px">📦</span>
        <div>
            <div class="card-title" style="margin:0">Sipariş Kayıtları</div>
            <div style="font-size:11px;color:var(--text2)">Siparişlerim → Excel İndir</div>
        </div>
    </div>
    <div style="font-size:11px;color:var(--text2);background:var(--bg3);border-radius:6px;padding:6px 10px;margin-bottom:12px">
        <code style="font-size:10px">SiparisKayitlari_*.xlsx</code>
    </div>
    <form method="POST" enctype="multipart/form-data" style="flex:1;display:flex;flex-direction:column">
    <input type="hidden" name="file_type" value="siparisler">
    <label for="file1" class="upload-zone" style="flex:1;min-height:80px;display:flex;flex-direction:column;align-items:center;justify-content:center">
        <div style="font-size:28px;margin-bottom:4px">📂</div>
        <div id="fn1" style="color:var(--text2);font-size:12px">Dosya seçin</div>
        <input type="file" id="file1" name="excel_file" accept=".xlsx" onchange="document.getElementById('fn1').textContent='✅ '+this.files[0].name">
    </label>
    <button type="submit" class="btn btn-primary" style="width:100%;margin-top:10px">📤 Yükle</button>
    </form>
    <div style="margin-top:10px;display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--text2)">
        <span><strong style="color:var(--text)"><?= number_format($stats['toplam_siparis'],0,',','.') ?></strong> kayıt</span>
        <?php if ($stats['toplam_siparis']>0): ?><a href="#" onclick="clearTable('siparisler')" style="color:var(--red);text-decoration:none;font-size:11px">🗑 Temizle</a><?php endif; ?>
    </div>
</div>

<!-- Reklam Raporu -->
<div class="card" style="display:flex;flex-direction:column">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
        <span style="font-size:24px">📣</span>
        <div>
            <div class="card-title" style="margin:0">Reklam Raporu</div>
            <div style="font-size:11px;color:var(--text2)">Reklamlarım → Rapor İndir</div>
        </div>
    </div>
    <div style="font-size:11px;color:var(--text2);background:var(--bg3);border-radius:6px;padding:6px 10px;margin-bottom:12px">
        <code style="font-size:10px">Ürün_Reklamları_Raporum_*.xlsx</code>
    </div>
    <form method="POST" enctype="multipart/form-data" style="flex:1;display:flex;flex-direction:column">
    <input type="hidden" name="file_type" value="reklamlar">
    <label for="file3" class="upload-zone" style="flex:1;min-height:80px;display:flex;flex-direction:column;align-items:center;justify-content:center">
        <div style="font-size:28px;margin-bottom:4px">📂</div>
        <div id="fn3" style="color:var(--text2);font-size:12px">Dosya seçin</div>
        <input type="file" id="file3" name="excel_file" accept=".xlsx" onchange="document.getElementById('fn3').textContent='✅ '+this.files[0].name">
    </label>
    <button type="submit" class="btn btn-primary" style="width:100%;margin-top:10px">📤 Yükle</button>
    </form>
    <div style="margin-top:10px;display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--text2)">
        <span><strong style="color:var(--text)"><?= $reklamSayisi ?></strong> kampanya</span>
        <?php if ($reklamSayisi>0): ?><a href="#" onclick="clearTable('reklamlar')" style="color:var(--red);text-decoration:none;font-size:11px">🗑 Temizle</a><?php endif; ?>
    </div>
</div>

<!-- Komisyon Tarifeleri -->
<div class="card" style="display:flex;flex-direction:column">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
        <span style="font-size:24px">🏷️</span>
        <div>
            <div class="card-title" style="margin:0">Komisyon Tarifeleri</div>
            <div style="font-size:11px;color:var(--text2)">Reklamlarım → Komisyon Tarifeleri</div>
        </div>
    </div>
    <div style="font-size:11px;color:var(--text2);background:var(--bg3);border-radius:6px;padding:6px 10px;margin-bottom:12px">
        <code style="font-size:10px">676274-*.xlsx</code>
    </div>
    <form method="POST" enctype="multipart/form-data" style="flex:1;display:flex;flex-direction:column">
    <input type="hidden" name="file_type" value="komisyon_tarifeleri">
    <label for="file4" class="upload-zone" style="flex:1;min-height:80px;display:flex;flex-direction:column;align-items:center;justify-content:center">
        <div style="font-size:28px;margin-bottom:4px">📂</div>
        <div id="fn4" style="color:var(--text2);font-size:12px">Dosya seçin</div>
        <input type="file" id="file4" name="excel_file" accept=".xlsx" onchange="document.getElementById('fn4').textContent='✅ '+this.files[0].name">
    </label>
    <button type="submit" class="btn btn-primary" style="width:100%;margin-top:10px">📤 Yükle</button>
    </form>
    <div style="margin-top:10px;display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--text2)">
        <span><strong style="color:var(--text)"><?= $komSayisi ?></strong> ürün tarifesi</span>
        <?php if ($komSayisi>0): ?><a href="#" onclick="clearTable('komisyon_tarifeleri')" style="color:var(--red);text-decoration:none;font-size:11px">🗑 Temizle</a><?php endif; ?>
    </div>
</div>


</div>

<?php elseif ($action === 'reklamlar'): ?>
<?php
$reklamAylar = [];
$reklamlar   = [];
try {
    // Ay bazlı özet
    $reklamAylar = DB::rows("
        SELECT
            CASE
                WHEN baslangic_tarihi REGEXP '^[0-9]{4}-[0-9]{2}'
                    THEN SUBSTRING(baslangic_tarihi,1,7)
                WHEN baslangic_tarihi REGEXP '^[0-9]{2}\\.[0-9]{2}\\.[0-9]{4}'
                    THEN CONCAT(SUBSTRING(baslangic_tarihi,7,4),'-',SUBSTRING(baslangic_tarihi,4,2))
                ELSE '0000-00'
            END AS yyaa,
            COUNT(*)         AS kampanya_sayisi,
            SUM(harcama)     AS toplam_harcama,
            SUM(toplam_ciro) AS toplam_ciro,
            SUM(tiklanma)    AS tiklanma,
            SUM(toplam_satis)AS satis,
            CASE WHEN SUM(harcama)>0 THEN ROUND(SUM(toplam_ciro)/SUM(harcama),2) ELSE 0 END AS roas
        FROM reklamlar WHERE magaza_id=? AND baslangic_tarihi != '-' AND baslangic_tarihi != ''
        GROUP BY yyaa ORDER BY yyaa DESC LIMIT 12
    ", [$magazaId]);

    // Kampanya tablosu
    $reklamlar = DB::rows("
        SELECT *, 
            CASE
                WHEN baslangic_tarihi REGEXP '^[0-9]{4}-[0-9]{2}'
                    THEN SUBSTRING(baslangic_tarihi,1,7)
                WHEN baslangic_tarihi REGEXP '^[0-9]{2}\\.[0-9]{2}\\.[0-9]{4}'
                    THEN CONCAT(SUBSTRING(baslangic_tarihi,7,4),'-',SUBSTRING(baslangic_tarihi,4,2))
                ELSE '0000-00'
            END AS yyaa
        FROM reklamlar WHERE magaza_id=?
        ORDER BY baslangic_tarihi DESC
    ", [$magazaId]);
} catch(PDOException $e) {}

$topHarcama  = array_sum(array_column($reklamlar,'harcama'));
$topCiro     = array_sum(array_column($reklamlar,'toplam_ciro'));
$topTiklanma = array_sum(array_column($reklamlar,'tiklanma'));
$topSatis    = array_sum(array_column($reklamlar,'toplam_satis'));
$topRoas     = $topHarcama > 0 ? round($topCiro / $topHarcama, 2) : 0;
$karli       = array_filter($reklamlar, fn($r) => (float)$r['roas'] >= 2);
$verimsiz    = array_filter($reklamlar, fn($r) => (float)$r['roas'] < 2 && (float)$r['roas'] >= 0);

// Seçili ay filtresi
$selAyReklam = $_GET['ray'] ?? '';
$filtreliReklamlar = $selAyReklam
    ? array_filter($reklamlar, fn($r) => $r['yyaa'] === $selAyReklam)
    : $reklamlar;

$ayIsimleriR = ['01'=>'Oca','02'=>'Şub','03'=>'Mar','04'=>'Nis','05'=>'May',
                '06'=>'Haz','07'=>'Tem','08'=>'Ağu','09'=>'Eyl','10'=>'Eki','11'=>'Kas','12'=>'Ara'];
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px">
    <div class="page-title" style="margin:0">📣 <span>Reklam Analizi</span></div>
    <a href="?action=veri_yukle" class="btn btn-sm" style="font-size:12px">📤 Reklam Excel Yükle</a>
</div>

<?php if (empty($reklamlar)): ?>
<div class="card"><div class="no-data"><div class="icon">📣</div>
    <p style="font-size:15px;margin-bottom:8px">Reklam verisi yüklenmemiş</p>
    <p style="color:var(--text2)"><a href="?action=veri_yukle" style="color:var(--primary)">Veri Yükle</a> sayfasından Trendyol reklam raporunu (.xlsx) yükleyin.</p>
</div></div>
<?php else: ?>

<!-- KPI -->
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:14px">
    <div class="kpi red"><div class="kpi-label">Toplam Harcama</div><div class="kpi-value"><?= fmtTL($topHarcama) ?></div><div class="kpi-sub"><?= count($reklamlar) ?> kampanya</div></div>
    <div class="kpi"><div class="kpi-label">Reklam Cirosu</div><div class="kpi-value"><?= fmtTL($topCiro) ?></div><div class="kpi-sub">Satış geliri</div></div>
    <div class="kpi <?= $topRoas>=2?'green':($topRoas>=1?'':'red') ?>">
        <div class="kpi-label">Ort. ROAS</div>
        <div class="kpi-value"><?= $topRoas ?>×</div>
        <div class="kpi-sub"><?= $topRoas>=2?'Karlı':($topRoas>=1?'Başabaş':'Zararlı') ?></div>
    </div>
    <div class="kpi"><div class="kpi-label">Tıklanma</div><div class="kpi-value"><?= number_format($topTiklanma,0,',','.') ?></div><div class="kpi-sub"><?= $topSatis ?> satış</div></div>
    <div class="kpi <?= count($karli)>count($verimsiz)?'green':'red' ?>">
        <div class="kpi-label">Karlı / Verimsiz</div>
        <div class="kpi-value"><span class="positive"><?= count($karli) ?></span> / <span class="negative"><?= count($verimsiz) ?></span></div>
        <div class="kpi-sub">ROAS ≥2 karlı</div>
    </div>
</div>

<!-- Karlı / Zararlı özet -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
    <div class="card" style="border-left:3px solid var(--green);padding:12px 16px">
        <div style="font-size:12px;color:var(--green);font-weight:500;margin-bottom:6px">✓ Karlı Kampanyalar (ROAS ≥ 2)</div>
        <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:8px">
            <?php foreach (array_slice($karli,0,6) as $r): ?>
            <span style="font-size:11px;background:rgba(74,222,128,.1);color:var(--green);padding:2px 8px;border-radius:5px"><?= htmlspecialchars($r['reklam_adi']) ?></span>
            <?php endforeach; ?>
        </div>
        <div style="font-size:12px;color:var(--text2)">
            Harcama: <strong style="color:var(--text)"><?= fmtTL(array_sum(array_column(array_values($karli),'harcama'))) ?></strong>
            → Ciro: <strong style="color:var(--green)"><?= fmtTL(array_sum(array_column(array_values($karli),'toplam_ciro'))) ?></strong>
        </div>
    </div>
    <div class="card" style="border-left:3px solid var(--red);padding:12px 16px">
        <div style="font-size:12px;color:var(--red);font-weight:500;margin-bottom:6px">✗ Verimsiz Kampanyalar (ROAS &lt; 2)</div>
        <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:8px">
            <?php foreach (array_slice($verimsiz,0,6) as $r): ?>
            <span style="font-size:11px;background:rgba(248,113,113,.1);color:var(--red);padding:2px 8px;border-radius:5px"><?= htmlspecialchars($r['reklam_adi']) ?></span>
            <?php endforeach; ?>
        </div>
        <div style="font-size:12px;color:var(--text2)">
            Harcama: <strong style="color:var(--text)"><?= fmtTL(array_sum(array_column(array_values($verimsiz),'harcama'))) ?></strong>
            → Ciro: <strong style="color:var(--red)"><?= fmtTL(array_sum(array_column(array_values($verimsiz),'toplam_ciro'))) ?></strong>
        </div>
    </div>
</div>

<!-- Ay Bazlı Tablo -->
<?php if (!empty($reklamAylar)): ?>
<div class="card" style="padding:0;overflow:hidden;margin-bottom:14px">
    <div style="padding:12px 18px;border-bottom:1px solid var(--border);font-size:13px;font-weight:500;color:var(--text)">
        📅 Ay Bazlı Reklam Özeti
    </div>
    <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse">
    <thead><tr style="background:var(--bg3)">
        <th style="text-align:left;padding:8px 16px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Ay</th>
        <th style="text-align:right;padding:8px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Kampanya</th>
        <th style="text-align:right;padding:8px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Harcama</th>
        <th style="text-align:right;padding:8px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Reklam Cirosu</th>
        <th style="text-align:right;padding:8px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">ROAS</th>
        <th style="text-align:right;padding:8px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Tıklanma</th>
        <th style="text-align:right;padding:8px 16px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Satış</th>
    </tr></thead><tbody>
    <?php foreach ($reklamAylar as $ay):
        $ayParts = explode('-', $ay['yyaa']);
        $ayAd = ($ayIsimleriR[$ayParts[1] ?? ''] ?? ($ayParts[1] ?? '')).' '.($ayParts[0] ?? '');
        $roas = (float)$ay['roas'];
        $roasKls = $roas >= 2 ? 'positive' : ($roas >= 1 ? 'neutral' : 'negative');
        $isFiltre = $selAyReklam === $ay['yyaa'];
    ?>
    <tr style="border-bottom:1px solid var(--border);background:<?= $isFiltre?'rgba(242,122,26,.06)':'' ?>;cursor:pointer"
        onclick="location.href='?action=reklamlar&ray=<?= urlencode($ay['yyaa']) ?>'">
        <td style="padding:9px 16px;font-weight:<?= $isFiltre?'600':'400' ?>;color:<?= $isFiltre?'var(--primary)':'var(--text)' ?>"><?= $ayAd ?></td>
        <td style="text-align:right;padding:9px 12px"><?= $ay['kampanya_sayisi'] ?></td>
        <td style="text-align:right;padding:9px 12px;color:var(--red);font-weight:500"><?= fmtTL($ay['toplam_harcama']) ?></td>
        <td style="text-align:right;padding:9px 12px;font-weight:500"><?= fmtTL($ay['toplam_ciro']) ?></td>
        <td style="text-align:right;padding:9px 12px"><span class="<?= $roasKls ?>"><?= $roas ?>×</span></td>
        <td style="text-align:right;padding:9px 12px"><?= number_format($ay['tiklanma'],0,',','.') ?></td>
        <td style="text-align:right;padding:9px 16px;font-weight:500"><?= $ay['satis'] ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>

<!-- Kampanya Detay Tablosu -->
<div class="card" style="padding:0;overflow:hidden">
    <div style="padding:12px 18px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
        <div style="font-size:13px;font-weight:500;color:var(--text)">📋 Kampanya Detayları
            <?php if ($selAyReklam): ?>
            <span style="font-size:12px;color:var(--primary);margin-left:8px">
                — <?= htmlspecialchars($selAyReklam) ?>
                <a href="?action=reklamlar" style="color:var(--text2);margin-left:6px;text-decoration:none;font-size:11px">✕ Temizle</a>
            </span>
            <?php endif; ?>
        </div>
        <span style="font-size:12px;color:var(--text2)"><?= count($filtreliReklamlar) ?> kampanya</span>
    </div>
    <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse">
    <thead><tr style="background:var(--bg3)">
        <th style="text-align:left;padding:8px 16px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Kampanya</th>
        <th style="text-align:left;padding:8px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Statü</th>
        <th style="text-align:left;padding:8px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Tarih</th>
        <th style="text-align:right;padding:8px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Harcama</th>
        <th style="text-align:right;padding:8px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Tıklanma</th>
        <th style="text-align:right;padding:8px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Satış</th>
        <th style="text-align:right;padding:8px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Reklam Ciro</th>
        <th style="text-align:right;padding:8px 12px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">ROAS</th>
        <th style="text-align:right;padding:8px 16px;font-size:11px;color:var(--text2);text-transform:uppercase;font-weight:500">Net Etki</th>
    </tr></thead><tbody>
    <?php foreach ($filtreliReklamlar as $r):
        $roas   = (float)$r['roas'];
        $netEtki= (float)$r['toplam_ciro'] - (float)$r['harcama'];
        $statuKls = $r['statu']==='Yayında'?'badge-blue':($r['statu']==='Tamamlandı'?'badge-green':'badge-orange');
        if ($roas >= 2)      $roasCls = 'positive';
        elseif ($roas >= 1)  $roasCls = 'neutral';
        else                 $roasCls = 'negative';
    ?>
    <tr style="border-bottom:1px solid var(--border)">
        <td style="padding:9px 16px;font-weight:500"><?= htmlspecialchars($r['reklam_adi']) ?></td>
        <td style="padding:9px 12px"><span class="badge <?= $statuKls ?>"><?= htmlspecialchars($r['statu']) ?></span></td>
        <td style="padding:9px 12px;font-size:11px;color:var(--text2)"><?= htmlspecialchars(substr($r['baslangic_tarihi'],0,10)) ?></td>
        <td style="text-align:right;padding:9px 12px;color:var(--red)"><?= fmtTL($r['harcama']) ?></td>
        <td style="text-align:right;padding:9px 12px"><?= number_format($r['tiklanma'],0,',','.') ?></td>
        <td style="text-align:right;padding:9px 12px"><?= $r['toplam_satis'] ?></td>
        <td style="text-align:right;padding:9px 12px"><?= fmtTL($r['toplam_ciro']) ?></td>
        <td style="text-align:right;padding:9px 12px;font-weight:600"><span class="<?= $roasCls ?>"><?= $roas ?>×</span></td>
        <td style="text-align:right;padding:9px 16px;font-weight:600">
            <span class="<?= $netEtki>=0?'positive':'negative' ?>"><?= fmtTL($netEtki) ?></span>
        </td>
    </tr>
    <?php endforeach; ?>
    <!-- Toplam -->
    <?php
    $fH = array_sum(array_column(array_values($filtreliReklamlar),'harcama'));
    $fC = array_sum(array_column(array_values($filtreliReklamlar),'toplam_ciro'));
    $fR = $fH > 0 ? round($fC/$fH,2) : 0;
    ?>
    <tr style="background:var(--bg3);border-top:2px solid var(--border);font-weight:600">
        <td style="padding:9px 16px">Toplam</td>
        <td></td><td></td>
        <td style="text-align:right;padding:9px 12px;color:var(--red)"><?= fmtTL($fH) ?></td>
        <td style="text-align:right;padding:9px 12px"><?= number_format(array_sum(array_column(array_values($filtreliReklamlar),'tiklanma')),0,',','.') ?></td>
        <td style="text-align:right;padding:9px 12px"><?= array_sum(array_column(array_values($filtreliReklamlar),'toplam_satis')) ?></td>
        <td style="text-align:right;padding:9px 12px"><?= fmtTL($fC) ?></td>
        <td style="text-align:right;padding:9px 12px"><span class="<?= $fR>=2?'positive':($fR>=1?'neutral':'negative') ?>"><?= $fR ?>×</span></td>
        <td style="text-align:right;padding:9px 16px"><span class="<?= ($fC-$fH)>=0?'positive':'negative' ?>"><?= fmtTL($fC-$fH) ?></span></td>
    </tr>
    </tbody></table></div>
</div>
<?php endif; ?>

<?php elseif ($action === 'komisyon'): ?>
<?php
$komUrunler = [];
try {
    $komUrunler = DB::rows("
        SELECT kt.*,
               MAX(tu.image_url)       AS image_url,
               MAX(tu.ty_id)           AS ty_id,
               MAX(m.birim_maliyet)    AS birim_maliyet,
               MAX(m.kargo_maliyeti)   AS kargo_maliyeti,
               MAX(m.paket_maliyeti)   AS paket_maliyeti,
               MAX(m.diger_maliyet)    AS diger_maliyet
        FROM komisyon_tarifeleri kt
        LEFT JOIN trendyol_urunler tu ON tu.magaza_id = kt.magaza_id
            AND (tu.barcode = kt.barcode
              OR tu.stock_code = kt.barcode
              OR tu.barcode = kt.stok_kodu
              OR tu.stock_code = kt.stok_kodu
              OR tu.stock_code = kt.model_kodu)
        LEFT JOIN maliyetler m ON m.ty_urun_id = tu.ty_id AND m.magaza_id = kt.magaza_id
        WHERE kt.magaza_id = ?
        GROUP BY kt.id, kt.urun_adi, kt.barcode, kt.stok_kodu, kt.beden, kt.model_kodu,
                 kt.kategori, kt.marka, kt.stok, kt.fiyat_limit_1, kt.fiyat_limit_2,
                 kt.fiyat_limit_3, kt.komisyon_1, kt.komisyon_2, kt.komisyon_3, kt.komisyon_4,
                 kt.guncel_fiyat, kt.guncel_komisyon, kt.guncel_tsf, kt.magaza_id, kt.yukleme_tarihi
        ORDER BY kt.urun_adi
    ", [$magazaId]);
} catch(PDOException $e) {}

// Kargo bedeli fiyata göre kademeli
$kargoTier = function(float $fiyat): float {
    if ($fiyat <= 0)   return 0.0;
    if ($fiyat <= 200) return 41.0;
    if ($fiyat <= 350) return 78.0;
    return 98.0;
};

$hesapla = function(float $fiyat, float $kom, float $birim, float $paket, float $diger, callable $kargoFn): array {
    $kargo   = $kargoFn($fiyat);
    $net     = $fiyat > 0 ? $fiyat * (1 - $kom/100) : 0;
    $maliyet = $birim + $kargo + $paket + $diger;
    $kar     = $net - $maliyet;
    $marj    = $fiyat > 0 ? round($kar / $fiyat * 100, 1) : 0;
    return ['kar'=>round($kar,2),'marj'=>$marj,'net'=>round($net,2),'kargo'=>$kargo,'maliyet'=>round($maliyet,2)];
};
?>
<style>
.kom-wrap{background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:0}
.kom-header{display:grid;grid-template-columns:280px repeat(4,1fr);background:var(--bg3);border-bottom:1px solid var(--border);padding:10px 16px;font-size:11px;font-weight:500;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;align-items:center}
.kom-row{display:grid;grid-template-columns:280px repeat(4,1fr);border-bottom:1px solid var(--border);min-height:130px}
.kom-row:last-child{border-bottom:none}
.kom-urun{padding:16px 14px;display:flex;align-items:flex-start;gap:10px;background:var(--card)}
.kom-urun-img{width:48px;height:48px;object-fit:cover;border-radius:8px;flex-shrink:0;background:var(--bg3)}
.kom-urun-info .name{font-size:12px;font-weight:500;color:var(--text);margin-bottom:4px;line-height:1.4}
.kom-urun-info .meta{font-size:10px;color:var(--text2);margin-bottom:6px}
.kom-urun-info .chips{display:flex;flex-direction:column;gap:4px;margin-top:4px}
.kom-urun-info .chip{font-size:10px;padding:3px 8px;border-radius:5px;display:inline-flex;align-items:center;gap:4px;width:fit-content}
.chip-mal{background:var(--bg3);color:var(--text2);border:1px solid var(--border)}
.chip-gun{background:rgba(46,204,113,.12);color:var(--green);border:1px solid rgba(46,204,113,.25)}
.chip-kar{background:rgba(242,122,26,.12);color:var(--primary);border:1px solid rgba(242,122,26,.25)}
.chip-warn{background:rgba(241,196,15,.12);color:var(--yellow);border:1px solid rgba(241,196,15,.25)}
.kom-cell{padding:10px 7px;border-left:1px solid var(--border)}
.sev-card{border-radius:10px;overflow:hidden;height:100%;display:flex;flex-direction:column;border:1px solid var(--border);background:var(--bg2);position:relative}
.sev-card.zarar{border-color:rgba(231,76,60,.5)}
.sev-card.karli{border-color:var(--border)}
.sev-card.en-iyi{border-color:rgba(46,204,113,.5)}
.sev-card.oneri{border-color:rgba(99,102,241,.6);border-style:dashed;border-width:2px}
.sev-badge{position:absolute;top:-1px;left:50%;transform:translateX(-50%);z-index:2;padding:2px 10px;border-radius:0 0 8px 8px;font-size:9px;font-weight:600;white-space:nowrap}
.badge-eniyi{background:#22c55e;color:#14532d}
.badge-oneri{background:#6366f1;color:#fff}
.sev-top{padding:8px 10px 5px;background:var(--bg2)}
.sev-aralik{font-size:11px;font-weight:500;color:var(--text)}
.sev-kom{display:inline-block;margin-top:3px;padding:2px 7px;border-radius:4px;font-size:10px;background:rgba(242,122,26,.18);color:var(--primary)}
.sev-kar-box{margin:0 7px;padding:8px 5px;border-radius:8px;text-align:center;flex:1;display:flex;flex-direction:column;justify-content:center}
.sev-kar-box.pos{background:rgba(46,204,113,.1)}
.sev-kar-box.neg{background:rgba(231,76,60,.1)}
.sev-kar-box.empty{background:var(--bg3)}
.sev-kar-val{font-size:17px;font-weight:600}
.sev-kar-val.pos{color:var(--green)}
.sev-kar-val.neg{color:var(--red)}
.sev-marj{font-size:10px;color:var(--text2);margin-top:2px}
.sev-btn{margin:5px 7px 7px;border:1px solid var(--border);border-radius:7px;padding:4px;font-size:11px;color:var(--text2);background:var(--bg3);cursor:pointer;text-align:center}
.sev-btn.guncel{border-color:var(--primary);color:var(--primary);background:rgba(242,122,26,.1)}
.sev-btn.oneri-btn{border-color:rgba(99,102,241,.5);color:#818cf8}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
    <div class="page-title" style="margin:0;color:inherit">🏷️ <span>Komisyon Tarifeleri</span></div>
    <a href="?action=veri_yukle" class="btn btn-sm" style="font-size:12px">📤 Excel Yükle</a>
</div>

<?php if (empty($komUrunler)): ?>
<div class="card"><div class="no-data"><div class="icon">🏷️</div>
    <p style="font-size:15px;margin-bottom:8px">Komisyon tarifesi yüklenmemiş</p>
    <p style="color:var(--text2)"><a href="?action=veri_yukle" style="color:var(--primary)">Veri Yükle</a> sayfasından Trendyol komisyon tarifeleri Excel'ini yükleyin.</p>
</div></div>
<?php else: ?>

<div class="kom-wrap">
<div class="kom-header">
    <div>Ürün Bilgisi</div>
    <div style="text-align:center">Seviye 1</div>
    <div style="text-align:center">Seviye 2</div>
    <div style="text-align:center">Seviye 3</div>
    <div style="text-align:center">Seviye 4</div>
</div>

<?php foreach ($komUrunler as $u):
    $birim = (float)($u['birim_maliyet']??0);
    $kargo = (float)($u['kargo_maliyeti']??0);
    $paket = (float)($u['paket_maliyeti']??0);
    $diger = (float)($u['diger_maliyet']??0);
    $hasMaliyet = ($birim + $kargo) > 0;

    $limitler = [(float)$u['fiyat_limit_1'], (float)$u['fiyat_limit_2'], (float)$u['fiyat_limit_3'], 0];
    $komlar   = [(float)$u['komisyon_1'], (float)$u['komisyon_2'], (float)$u['komisyon_3'], (float)$u['komisyon_4']];
    $ustLimit = [null, $limitler[0]-0.01, $limitler[1]-0.01, $limitler[2]-0.01];

    // Temsilci fiyat: seviyenin alt sınırı
    $temsilF = [$limitler[0], $limitler[1]>0?$limitler[1]:0, $limitler[2]>0?$limitler[2]:0, 0];

    // Güncel seviye + kargo
    $gf = (float)$u['guncel_fiyat'];
    $guncelSi = 3;
    if ($gf >= $limitler[0]) $guncelSi = 0;
    elseif ($gf >= $limitler[1] && $limitler[1]>0) $guncelSi = 1;
    elseif ($gf >= $limitler[2] && $limitler[2]>0) $guncelSi = 2;
    $kargoGuncel = $kargoTier($gf);

    $seviyeler = [];
    for ($si=0; $si<4; $si++) {
        $seviyeler[] = $hesapla($temsilF[$si], $komlar[$si], $birim, $paket, $diger, $kargoTier);
    }

    // En iyi ve öneri
    $enIyiIdx = null; $oneriIdx = null;
    if ($hasMaliyet) {
        $maxKar = PHP_FLOAT_MIN;
        foreach ($seviyeler as $si=>$sv) {
            if ($sv['kar'] > $maxKar) { $maxKar=$sv['kar']; $enIyiIdx=$si; }
        }
        $minKom = PHP_FLOAT_MAX;
        foreach ($seviyeler as $si=>$sv) {
            if ($sv['kar']>0 && $komlar[$si]<$minKom) { $minKom=$komlar[$si]; $oneriIdx=$si; }
        }
        if ($oneriIdx===$enIyiIdx) $oneriIdx=null;
    }
?>
<div class="kom-row">
    <!-- Ürün Bilgisi -->
    <div class="kom-urun">
        <?php if ($u['image_url']): ?>
        <img src="<?= htmlspecialchars($u['image_url']) ?>" class="kom-urun-img" loading="lazy">
        <?php else: ?>
        <div class="kom-urun-img" style="display:flex;align-items:center;justify-content:center;font-size:22px">📦</div>
        <?php endif; ?>
        <div class="kom-urun-info" style="flex:1;min-width:0">
            <div class="name"><?= htmlspecialchars($u['urun_adi']) ?></div>
            <div class="meta">▮▮▮ <?= htmlspecialchars(substr($u['barcode'],0,16)) ?> &nbsp;·&nbsp; <?= number_format((int)$u['stok'],0,',','.') ?> adet</div>
            <table style="margin-top:8px;width:100%;border-collapse:collapse;font-size:11px">
                <tr>
                    <td style="color:var(--text2);padding:3px 0;width:50%">🏷 Maliyet</td>
                    <td style="font-weight:500;color:var(--text);text-align:right">
                        <?= $birim>0 ? fmtTL($birim) : '<span style="color:var(--red);font-size:10px">—</span>' ?>
                    </td>
                </tr>
                <tr style="border-top:1px solid var(--border)">
                    <td style="color:var(--text2);padding:3px 0">✦ Güncel Fiyat</td>
                    <td style="font-weight:500;color:var(--green);text-align:right"><?= fmtTL((float)$u['guncel_fiyat']) ?></td>
                </tr>
                <?php if (!empty($u['guncel_tsf']) && (float)$u['guncel_tsf'] > 0): ?>
                <tr style="border-top:1px solid var(--border)">
                    <td style="color:var(--text2);padding:3px 0">✦ Güncel TSF</td>
                    <td style="font-weight:500;color:var(--green);text-align:right"><?= fmtTL((float)$u['guncel_tsf']) ?></td>
                </tr>
                <?php endif; ?>
                <tr style="border-top:1px solid var(--border)">
                    <td style="color:var(--text2);padding:3px 0">🚚 Kargo</td>
                    <td style="font-weight:500;color:var(--primary);text-align:right"><?= fmtTL($kargoGuncel) ?></td>
                </tr>
                <?php if (!$hasMaliyet): ?>
                <tr style="border-top:1px solid var(--border)">
                    <td colspan="2" style="padding-top:5px"><a href="?action=ty_urunler" style="font-size:10px;color:var(--yellow);text-decoration:none">⚠ Maliyet gir</a></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- 4 Seviye -->
    <?php for ($si=0; $si<4; $si++):
        $sv = $seviyeler[$si];
        $isEnIyi = ($enIyiIdx===$si);
        $isOneri = ($oneriIdx===$si);
        $isKarli = $hasMaliyet && $sv['kar']>0;
        $isZarar = $hasMaliyet && $sv['kar']<=0;

        if ($si===0) $aralik = fmtTL($limitler[0]).' +';
        elseif ($si===3 && $ustLimit[3]>0) $aralik = '0 – '.fmtTL($ustLimit[3]);
        elseif ($si===3) $aralik = '—';
        else $aralik = fmtTL($limitler[$si]).' – '.fmtTL($ustLimit[$si]);

        $cardCls = 'sev-card'.($isZarar?' zarar':($isOneri?' oneri':($isEnIyi?' en-iyi':' karli')));
    ?>
    <div class="kom-cell">
        <div class="<?= $cardCls ?>">
            <?php if ($isEnIyi): ?>
            <div class="sev-badge badge-eniyi">⭐ EN İYİ</div>
            <?php elseif ($isOneri): ?>
            <div class="sev-badge badge-oneri">💡 ÖNERİ</div>
            <?php endif; ?>

            <div class="sev-top" style="padding-top:<?= ($isEnIyi||$isOneri)?'18px':'8px' ?>">
                <div style="display:flex;justify-content:space-between;align-items:flex-start">
                    <div>
                        <div class="sev-aralik"><?= $aralik ?></div>
                        <span class="sev-kom" style="margin-top:3px;display:inline-block">%<?= $komlar[$si] ?></span>
                    </div>
                </div>
            </div>

            <?php if ($hasMaliyet): ?>
            <div class="sev-kar-box <?= $isKarli?'pos':'neg' ?>">
                <div class="sev-kar-val <?= $isKarli?'pos':'neg' ?>">
                    <?= ($sv['kar']>=0?'+':'').fmtTL($sv['kar']) ?>
                </div>
                <div class="sev-marj">%<?= $sv['marj'] ?> marj</div>
            </div>
            <?php else: ?>
            <div class="sev-kar-box" style="background:#f9fafb">
                <div style="font-size:13px;color:#6b7280"><?= fmtTL($sv['net']) ?></div>
                <div class="sev-marj">Maliyet yok</div>
            </div>
            <?php endif; ?>

            <div class="sev-btn <?= $si===$guncelSi?'guncel':($isOneri?'oneri-btn':'') ?>">
                <?= $si===$guncelSi ? '✓ Mevcut' : ($isOneri ? '💡 Önerilen' : '—') ?>
            </div>
        </div>
    </div>
    <?php endfor; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($action === 'odemeler'): ?>
<?php
// ---- Ödeme Detay: Upload handler ----
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form']??'')==='odeme_detay_upload') {
    // Yukarıdaki global handler halletti, burası boş kalabilir.
}

// ---- Filtreler ----
$odDonem  = $_GET['donem']  ?? '';
$odTip    = $_GET['tip']    ?? '';
$odSrch   = trim($_GET['q'] ?? '');
$odPg     = max(1,(int)($_GET['p'] ?? 1));
$odPp     = 100;
$odOff    = ($odPg-1)*$odPp;

// Mevcut dönemler (donem_tagi boş olabileceğini de hesaba kat)
$donemler    = DB::rows("SELECT DISTINCT donem_tagi FROM odeme_detay WHERE magaza_id=? AND donem_tagi!='' ORDER BY donem_tagi DESC", [$magazaId]);
$bosDonemVar = (int)DB::scalar("SELECT COUNT(*) FROM odeme_detay WHERE magaza_id=? AND (donem_tagi='' OR donem_tagi IS NULL)", [$magazaId]);

// En son dönemi varsayılan yap; dönem yoksa boş tag'li veriyi göster
if (!$odDonem) {
    if (!empty($donemler))     $odDonem = $donemler[0]['donem_tagi'];
    elseif ($bosDonemVar > 0)  $odDonem = '_bos';
}

// Filtre koşulları
$odConds = ["magaza_id=?"];
$odPrms  = [$magazaId];
if ($odDonem === '_bos') {
    $odConds[] = "(donem_tagi='' OR donem_tagi IS NULL)";
} elseif ($odDonem) {
    $odConds[] = "donem_tagi=?"; $odPrms[] = $odDonem;
}
if ($odTip)   { $odConds[] = "islem_tipi=?"; $odPrms[] = $odTip; }
if ($odSrch)  { $odConds[] = "(siparis_no LIKE ? OR urun_adi LIKE ? OR musteri LIKE ?)"; $odPrms = array_merge($odPrms, ["%$odSrch%","%$odSrch%","%$odSrch%"]); }
$odWhere = 'WHERE '.implode(' AND ',$odConds);

// Özet istatistikler — her zaman hesapla
$ozet = [];
{
    $ozConds = ["magaza_id=?"];
    $ozPrms  = [$magazaId];
    if ($odDonem === '_bos') {
        $ozConds[] = "(donem_tagi='' OR donem_tagi IS NULL)";
    } elseif ($odDonem) {
        $ozConds[] = "donem_tagi=?"; $ozPrms[] = $odDonem;
    }
    $ozWhere = 'WHERE '.implode(' AND ',$ozConds);

    $ozet = DB::row("
        SELECT
            COUNT(*) AS toplam_kalem,
            COALESCE(SUM(CASE WHEN islem_tipi='Satış' THEN toplam_tutar ELSE 0 END),0)    AS satis_tutari,
            COALESCE(SUM(CASE WHEN islem_tipi='Satış' THEN satici_hakedis ELSE 0 END),0)  AS satis_hakedis,
            COALESCE(SUM(CASE WHEN islem_tipi='İade'  THEN satici_hakedis ELSE 0 END),0)  AS iade_hakedis,
            COALESCE(SUM(CASE WHEN islem_tipi='Kupon' THEN satici_hakedis ELSE 0 END),0)  AS kupon_hakedis,
            COALESCE(SUM(CASE WHEN islem_tipi='Reklam Bedeli' THEN satici_hakedis ELSE 0 END),0) AS reklam_hakedis,
            COALESCE(SUM(CASE WHEN islem_tipi='Kargo Fatura' THEN satici_hakedis ELSE 0 END),0)  AS kargo_hakedis,
            COALESCE(SUM(CASE WHEN islem_tipi='Platform Hizmet Bedeli' THEN satici_hakedis ELSE 0 END),0) AS platform_hakedis,
            COALESCE(SUM(CASE WHEN islem_tipi NOT IN ('Satış','İade','Kupon','Reklam Bedeli','Kargo Fatura','Platform Hizmet Bedeli') THEN satici_hakedis ELSE 0 END),0) AS diger_hakedis,
            COALESCE(SUM(satici_hakedis),0) AS net_hakedis,
            COALESCE(SUM(stopaj),0) AS toplam_stopaj
        FROM odeme_detay $ozWhere
    ", $ozPrms);
}

// İşlem tipi dağılımı
$tipDagilim = DB::rows("
    SELECT islem_tipi, COUNT(*) as adet, COALESCE(SUM(satici_hakedis),0) as toplam
    FROM odeme_detay $odWhere
    GROUP BY islem_tipi ORDER BY adet DESC
", $odPrms);

// Sipariş bazlı özet (sadece Satış+İade+Kupon olan siparişler)
$sipOzetConds = ["od.magaza_id=?", "od.islem_tipi IN ('Satış','İade','Kupon')", "od.siparis_no!=''"];
$sipOzetPrms  = [$magazaId];
if ($odDonem === '_bos') {
    $sipOzetConds[] = "(od.donem_tagi='' OR od.donem_tagi IS NULL)";
} elseif ($odDonem) {
    $sipOzetConds[] = "od.donem_tagi=?"; $sipOzetPrms[] = $odDonem;
}
if ($odSrch)  { $sipOzetConds[] = "(od.siparis_no LIKE ? OR od.urun_adi LIKE ?)"; $sipOzetPrms = array_merge($sipOzetPrms, ["%$odSrch%","%$odSrch%"]); }
$sipOzetWhere = 'WHERE '.implode(' AND ',$sipOzetConds);

$sipTot = (int)DB::scalar("SELECT COUNT(DISTINCT od.siparis_no) FROM odeme_detay od $sipOzetWhere", $sipOzetPrms);

// Sayfalama için — sipariş bazlı
$sipListConds = $sipOzetConds;
$sipListPrms  = $sipOzetPrms;
$sipListWhere = $sipOzetWhere;

$siparisOzet = DB::rows("
    SELECT
        od.siparis_no,
        GROUP_CONCAT(DISTINCT od.islem_tipi ORDER BY od.islem_tipi SEPARATOR ',') AS tipler,
        MIN(od.siparis_tarihi) AS siparis_tarihi,
        MAX(od.islem_tarihi)   AS islem_tarihi,
        MAX(od.vade_tarihi)    AS vade_tarihi,
        COALESCE(SUM(CASE WHEN od.islem_tipi='Satış' THEN od.toplam_tutar ELSE 0 END),0) AS brut_tutar,
        COALESCE(SUM(od.satici_hakedis),0) AS net_hakedis,
        GROUP_CONCAT(DISTINCT SUBSTRING(od.urun_adi,1,60) ORDER BY od.islem_tarihi SEPARATOR ' / ') AS urunler
    FROM odeme_detay od
    $sipListWhere
    GROUP BY od.siparis_no
    ORDER BY MAX(od.vade_tarihi) ASC
    LIMIT $odPp OFFSET $odOff
", $sipListPrms);

$odToplam = (int)DB::scalar("SELECT COUNT(*) FROM odeme_detay $odWhere", $odPrms);
$odSayfa  = max(1, (int)ceil($sipTot / $odPp));

$today = date('Y-m-d');
$odemeDetaySayisi = (int)DB::scalar("SELECT COUNT(*) FROM odeme_detay WHERE magaza_id=?", [$magazaId]);
?>

<div class="page-title">💰 <span>Ödemeler</span>
<?php if ($odDonem): ?>
<span style="font-size:13px;color:var(--text2);font-weight:400;margin-left:10px"><?= htmlspecialchars($odDonem) ?> dönemi</span>
<?php endif; ?>
</div>

<?php if ($odemeDetaySayisi === 0): ?>
<!-- Veri yok — yükleme ekranı -->
<div class="card" style="text-align:center;padding:60px 40px">
    <div style="font-size:48px;margin-bottom:16px">💳</div>
    <div style="font-size:17px;font-weight:600;margin-bottom:8px">Henüz ödeme verisi yüklenmedi</div>
    <div style="color:var(--text2);margin-bottom:24px;font-size:13px">
        Trendyol Satıcı Paneli → <strong>Finansal İşlemler</strong> → <strong>Ödeme Detayı</strong>'ndan<br>
        <code style="background:var(--bg3);padding:2px 6px;border-radius:4px">OdemeDetay_TR_*.xlsx</code> dosyasını indirin ve yükleyin.
    </div>
    <form method="POST" enctype="multipart/form-data" style="display:inline-block">
        <input type="hidden" name="file_type" value="odeme_detay">
        <label style="cursor:pointer">
            <div class="btn btn-primary" style="font-size:14px;padding:12px 28px">📂 OdemeDetay Excel Yükle</div>
            <input type="file" name="excel_file" accept=".xlsx" onchange="this.form.submit()" style="display:none">
        </label>
    </form>
    <?php if ($apiOk): ?>
    <div style="margin-top:16px;color:var(--text2);font-size:13px">ya da</div>
    <button class="btn btn-success" style="margin-top:12px;font-size:14px;padding:12px 28px" onclick="document.getElementById('odApiPanel').classList.remove('hidden');this.closest('.card').insertAdjacentElement('afterend',document.getElementById('odApiPanel'))">
        📡 API'den Otomatik Çek
    </button>
    <?php endif; ?>
</div>

<!-- API Paneli (boş ekran için) -->
<?php if ($apiOk): ?>
<div id="odApiPanel" class="hidden card" style="margin-top:16px;padding:16px 20px">
    <div style="font-size:14px;font-weight:600;margin-bottom:12px">📡 API'den Ödeme Verisi Çek</div>
    <div style="font-size:12px;color:var(--text2);margin-bottom:14px;line-height:1.8">
        Trendyol Cari Hesap Ekstresi API'sinden tüm finansal kalemleri otomatik çeker. Tarihi seçin ve çekin.
    </div>
    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="margin:0">
            <label style="font-size:11px;color:var(--text2)">Başlangıç</label>
            <input type="date" id="odSyncStart" value="<?= date('Y-m-d', strtotime('first day of this month')) ?>"
                style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:8px;font-size:13px">
        </div>
        <div class="form-group" style="margin:0">
            <label style="font-size:11px;color:var(--text2)">Bitiş</label>
            <input type="date" id="odSyncEnd" value="<?= date('Y-m-d') ?>"
                style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:8px;font-size:13px">
        </div>
        <button class="btn btn-primary" onclick="syncOdemeDetay()" id="odSyncBtn">📥 Verileri Çek</button>
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-self:flex-end">
            <button class="btn" style="background:var(--bg3);color:var(--text2);font-size:11px" onclick="odSetRange(1)">Bu Ay</button>
            <button class="btn" style="background:var(--bg3);color:var(--text2);font-size:11px" onclick="odSetRange(2)">Geçen Ay</button>
            <button class="btn" style="background:var(--bg3);color:var(--text2);font-size:11px" onclick="odSetRange(3)">Son 30 Gün</button>
            <button class="btn" style="background:var(--bg3);color:var(--text2);font-size:11px" onclick="odSetRange(4)">Son 90 Gün</button>
        </div>
    </div>
    <div id="odSyncLog" style="margin-top:12px;font-size:12px;color:var(--text2);display:none"></div>
</div>
<script>
function odSetRange(t) {
    var now = new Date();
    var s, e = now.toISOString().slice(0,10);
    if (t===1) { s = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0,10); }
    else if (t===2) { var d = new Date(now.getFullYear(), now.getMonth()-1, 1); s = d.toISOString().slice(0,10); e = new Date(now.getFullYear(), now.getMonth(), 0).toISOString().slice(0,10); }
    else if (t===3) { s = new Date(Date.now()-30*86400000).toISOString().slice(0,10); }
    else if (t===4) { s = new Date(Date.now()-90*86400000).toISOString().slice(0,10); }
    document.getElementById('odSyncStart').value = s;
    document.getElementById('odSyncEnd').value   = e;
}
function syncOdemeDetay() {
    var btn = document.getElementById('odSyncBtn');
    var log = document.getElementById('odSyncLog');
    var s   = document.getElementById('odSyncStart').value;
    var e   = document.getElementById('odSyncEnd').value;
    if (!s || !e) { toast('Tarih aralığı seçin', false); return; }
    btn.disabled = true; btn.textContent = '⏳ Çekiliyor...';
    log.style.display = 'block';
    log.innerHTML = '<span style="color:var(--yellow)">⏳ API\'den veriler çekiliyor, büyük aralıklarda birkaç dakika sürebilir...</span>';
    post({action:'sync_odeme_detay', start_date:s, end_date:e}).then(function(d) {
        btn.disabled = false; btn.textContent = '📥 Verileri Çek';
        if (d.error) {
            log.innerHTML = '<span style="color:var(--red)">❌ ' + d.error + '</span>';
        } else {
            var msg = '✅ Tamamlandı — ' + (d.inserted||0) + ' yeni kayıt, ' + (d.updated||0) + ' güncellendi';
            if (d.errors && d.errors.length) msg += '<br><span style="color:var(--yellow)">⚠️ ' + d.errors.join(' | ') + '</span>';
            log.innerHTML = '<span style="color:var(--green)">' + msg + '</span>';
            setTimeout(function(){ location.reload(); }, 1800);
        }
    }).catch(function() {
        btn.disabled = false; btn.textContent = '📥 Verileri Çek';
        log.innerHTML = '<span style="color:var(--red)">❌ Bağlantı hatası</span>';
    });
}
</script>
<?php endif; ?>

<?php else: ?>

<!-- Dönem seçici + upload -->
<div style="display:flex;gap:10px;align-items:center;margin-bottom:20px;flex-wrap:wrap">
    <div style="display:flex;gap:6px;flex-wrap:wrap">
    <?php foreach($donemler as $d): ?>
        <a href="?action=odemeler&donem=<?= urlencode($d['donem_tagi']) ?>"
           class="tab-btn <?= $odDonem===$d['donem_tagi']?'active':'' ?>"><?= htmlspecialchars($d['donem_tagi']) ?></a>
    <?php endforeach; ?>
    <?php if ($bosDonemVar > 0): ?>
        <a href="?action=odemeler&donem=_bos"
           class="tab-btn <?= $odDonem==='_bos'?'active':'' ?>">Dönemsiz (<?= $bosDonemVar ?>)</a>
    <?php endif; ?>
    <?php if (count($donemler) > 1 || $bosDonemVar > 0): ?>
        <a href="?action=odemeler&donem=" class="tab-btn <?= $odDonem===''?'active':'' ?>">Tümü</a>
    <?php endif; ?>
    </div>
    <div style="margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <?php if ($apiOk): ?>
        <button class="btn btn-primary" style="font-size:12px" onclick="document.getElementById('odApiPanel').classList.toggle('hidden')">📡 API'den Çek</button>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="file_type" value="odeme_detay">
        <label style="cursor:pointer">
            <div class="btn btn-success" style="font-size:12px">📂 Excel Yükle</div>
            <input type="file" name="excel_file" accept=".xlsx" onchange="this.form.submit()" style="display:none">
        </label>
    </form>
    <?php if ($odemeDetaySayisi>0): ?>
    <a href="#" onclick="clearTable('odeme_detay')" class="btn btn-danger" style="font-size:12px">🗑 Tümünü Sil</a>
    <?php endif; ?>
    </div>
</div>

<!-- API Sync Paneli -->
<?php if ($apiOk): ?>
<div id="odApiPanel" class="hidden card" style="margin-bottom:16px;padding:16px 20px">
    <div style="font-size:14px;font-weight:600;margin-bottom:12px">📡 API'den Ödeme Verisi Çek</div>
    <div style="font-size:12px;color:var(--text2);margin-bottom:14px;line-height:1.8">
        Trendyol Cari Hesap Ekstresi API'sinden <strong>Satış, İade, İndirim, Kupon, Kargo, Platform Hizmet</strong> ve diğer tüm kalemleri çeker.
        API max <strong>15 günlük</strong> aralık kabul ettiğinden büyük aralıklar otomatik bölünür.
    </div>
    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="margin:0">
            <label style="font-size:11px;color:var(--text2)">Başlangıç Tarihi</label>
            <input type="date" id="odSyncStart" value="<?= date('Y-m-d', strtotime('first day of this month')) ?>"
                style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:8px;font-size:13px">
        </div>
        <div class="form-group" style="margin:0">
            <label style="font-size:11px;color:var(--text2)">Bitiş Tarihi</label>
            <input type="date" id="odSyncEnd" value="<?= date('Y-m-d') ?>"
                style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:7px 10px;border-radius:8px;font-size:13px">
        </div>
        <button class="btn btn-primary" onclick="syncOdemeDetay()" id="odSyncBtn">📥 Verileri Çek</button>
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-self:flex-end">
            <button class="btn" style="background:var(--bg3);color:var(--text2);font-size:11px" onclick="odSetRange(1)">Bu Ay</button>
            <button class="btn" style="background:var(--bg3);color:var(--text2);font-size:11px" onclick="odSetRange(2)">Geçen Ay</button>
            <button class="btn" style="background:var(--bg3);color:var(--text2);font-size:11px" onclick="odSetRange(3)">Son 30 Gün</button>
            <button class="btn" style="background:var(--bg3);color:var(--text2);font-size:11px" onclick="odSetRange(4)">Son 90 Gün</button>
        </div>
    </div>
    <div id="odSyncLog" style="margin-top:12px;font-size:12px;color:var(--text2);display:none"></div>
</div>
<script>
function odSetRange(t) {
    var now = new Date();
    var s, e = now.toISOString().slice(0,10);
    if (t===1) { s = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0,10); }
    else if (t===2) { var d = new Date(now.getFullYear(), now.getMonth()-1, 1); s = d.toISOString().slice(0,10); e = new Date(now.getFullYear(), now.getMonth(), 0).toISOString().slice(0,10); }
    else if (t===3) { s = new Date(Date.now()-30*86400000).toISOString().slice(0,10); }
    else if (t===4) { s = new Date(Date.now()-90*86400000).toISOString().slice(0,10); }
    document.getElementById('odSyncStart').value = s;
    document.getElementById('odSyncEnd').value   = e;
}
function syncOdemeDetay() {
    var btn = document.getElementById('odSyncBtn');
    var log = document.getElementById('odSyncLog');
    var s   = document.getElementById('odSyncStart').value;
    var e   = document.getElementById('odSyncEnd').value;
    if (!s || !e) { toast('Tarih aralığı seçin', false); return; }
    btn.disabled = true;
    btn.textContent = '⏳ Çekiliyor...';
    log.style.display = 'block';
    log.innerHTML = '<span style="color:var(--yellow)">⏳ API\'den veriler çekiliyor, lütfen bekleyin...</span>';
    post({action:'sync_odeme_detay', start_date:s, end_date:e}).then(function(d) {
        btn.disabled = false;
        btn.textContent = '📥 Verileri Çek';
        if (d.error) {
            log.innerHTML = '<span style="color:var(--red)">❌ ' + d.error + '</span>';
        } else {
            var msg = '✅ Tamamlandı — ' + (d.inserted||0) + ' yeni, ' + (d.updated||0) + ' güncellendi';
            if (d.errors && d.errors.length) msg += '<br><span style="color:var(--yellow)">⚠️ ' + d.errors.join(', ') + '</span>';
            log.innerHTML = '<span style="color:var(--green)">' + msg + '</span>';
            setTimeout(function(){ location.reload(); }, 1500);
        }
    }).catch(function(e) {
        btn.disabled = false;
        btn.textContent = '📥 Verileri Çek';
        log.innerHTML = '<span style="color:var(--red)">❌ Bağlantı hatası</span>';
    });
}
</script>
<?php endif; ?>

<!-- KPI Kartları -->
<?php if ($ozet): ?>
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fill,minmax(170px,1fr))">
    <div class="kpi green">
        <div class="kpi-label">Satış Hakediş</div>
        <div class="kpi-value positive"><?= fmtTL($ozet['satis_hakedis']) ?></div>
        <div class="kpi-sub">Brüt: <?= fmtTL($ozet['satis_tutari']) ?></div>
    </div>
    <div class="kpi red">
        <div class="kpi-label">Kargo Faturası</div>
        <div class="kpi-value negative"><?= fmtTL($ozet['kargo_hakedis']) ?></div>
        <div class="kpi-sub"><?= $ozet['satis_tutari']>0 ? '%'.number_format(abs($ozet['kargo_hakedis'])/$ozet['satis_tutari']*100,1,',','.') : '—' ?> oran</div>
    </div>
    <div class="kpi red">
        <div class="kpi-label">Reklam Bedeli</div>
        <div class="kpi-value negative"><?= fmtTL($ozet['reklam_hakedis']) ?></div>
        <div class="kpi-sub"><?= $ozet['satis_tutari']>0 ? '%'.number_format(abs($ozet['reklam_hakedis'])/$ozet['satis_tutari']*100,1,',','.') : '—' ?> oran</div>
    </div>
    <div class="kpi red">
        <div class="kpi-label">Platform Hizmet</div>
        <div class="kpi-value negative"><?= fmtTL($ozet['platform_hakedis']) ?></div>
        <div class="kpi-sub"><?= $ozet['satis_tutari']>0 ? '%'.number_format(abs($ozet['platform_hakedis'])/$ozet['satis_tutari']*100,1,',','.') : '—' ?> oran</div>
    </div>
    <div class="kpi" style="border-top:3px solid var(--text2)">
        <div class="kpi-label">İade + Kupon</div>
        <div class="kpi-value negative"><?= fmtTL($ozet['iade_hakedis'] + $ozet['kupon_hakedis']) ?></div>
        <div class="kpi-sub">Diğer: <?= fmtTL($ozet['diger_hakedis']) ?></div>
    </div>
    <div class="kpi <?= $ozet['net_hakedis']>=0?'green':'red' ?>">
        <div class="kpi-label">Net Hakediş</div>
        <div class="kpi-value <?= $ozet['net_hakedis']>=0?'positive':'negative' ?>"><?= fmtTL($ozet['net_hakedis']) ?></div>
        <div class="kpi-sub"><?= $ozet['satis_tutari']>0 ? '%'.number_format($ozet['net_hakedis']/$ozet['satis_tutari']*100,1,',','.') : '—' ?> oran</div>
    </div>
</div>

<!-- Kesinti tablosu + İşlem tipi dağılımı -->
<div class="grid-2" style="margin-bottom:20px">
<div class="card">
    <div class="card-title">📊 Hakediş Dağılımı</div>
    <table>
        <thead><tr><th>Kalem</th><th style="text-align:right">Tutar</th><th style="text-align:right">Oran</th></tr></thead>
        <tbody>
        <?php
        $base = abs($ozet['satis_hakedis']) ?: 1;
        $satirlar = [
            ['+ Satış Hakediş', $ozet['satis_hakedis'], 'positive'],
            ['− Kargo Faturası', $ozet['kargo_hakedis'], 'negative'],
            ['− Reklam Bedeli', $ozet['reklam_hakedis'], 'negative'],
            ['− Platform Hizmet', $ozet['platform_hakedis'], 'negative'],
            ['− İade', $ozet['iade_hakedis'], 'negative'],
            ['− Kupon İndirimi', $ozet['kupon_hakedis'], 'negative'],
            ['− Stopaj', $ozet['toplam_stopaj'], 'negative'],
            ['− Diğer Kesintiler', $ozet['diger_hakedis'], 'negative'],
        ];
        foreach ($satirlar as $s):
            if ($s[1] == 0) continue;
            $pct = round(abs($s[1])/$base*100,1);
        ?>
        <tr>
            <td><?= $s[0] ?></td>
            <td class="<?= $s[2] ?>" style="text-align:right"><?= fmtTL($s[1]) ?></td>
            <td style="text-align:right;color:var(--text2);font-size:12px">%<?= number_format($pct,1,',','.') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
        <tr style="border-top:2px solid var(--border)">
            <td style="font-weight:700">= Net Hakediş</td>
            <td class="<?= $ozet['net_hakedis']>=0?'positive':'negative' ?>" style="text-align:right;font-weight:700"><?= fmtTL($ozet['net_hakedis']) ?></td>
            <td style="text-align:right;color:var(--text2);font-size:12px">%<?= $ozet['satis_tutari']>0?number_format($ozet['net_hakedis']/$ozet['satis_tutari']*100,1,',','.'):'—' ?></td>
        </tr>
        </tfoot>
    </table>
</div>
<div class="card">
    <div class="card-title">🔖 İşlem Tipi Dağılımı</div>
    <table>
        <thead><tr><th>Tür</th><th style="text-align:right">Adet</th><th style="text-align:right">Hakediş</th></tr></thead>
        <tbody>
        <?php foreach($tipDagilim as $t): ?>
        <tr>
            <td>
                <a href="?action=odemeler&donem=<?= urlencode($odDonem) ?>&tip=<?= urlencode($t['islem_tipi']) ?>"
                   style="color:var(--text);text-decoration:none;font-size:13px">
                <?php
                $tipRenk = 'badge-gray';
                if ($t['islem_tipi']==='Satış') $tipRenk = 'badge-green';
                elseif ($t['islem_tipi']==='İade') $tipRenk = 'badge-red';
                elseif ($t['islem_tipi']==='Kupon') $tipRenk = 'badge-yellow';
                elseif (strpos($t['islem_tipi'],'Reklam') !== false) $tipRenk = 'badge-orange';
                ?>
                <span class="badge <?= $tipRenk ?>"><?= htmlspecialchars($t['islem_tipi']) ?></span>
                </a>
            </td>
            <td style="text-align:right;color:var(--text2)"><?= number_format($t['adet'],0,',','.') ?></td>
            <td class="<?= $t['toplam']>=0?'positive':'negative' ?>" style="text-align:right"><?= fmtTL($t['toplam']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>
<?php endif; ?>

<!-- Sipariş bazlı tablo -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
        <div class="card-title" style="margin:0">📦 Sipariş Bazlı Ödeme Durumu
            <span style="font-size:12px;color:var(--text2);font-weight:400">(<?= number_format($sipTot,0,',','.') ?> sipariş)</span>
        </div>
        <form method="GET" style="display:flex;gap:8px;align-items:center">
            <input type="hidden" name="action" value="odemeler">
            <input type="hidden" name="donem"  value="<?= htmlspecialchars($odDonem) ?>">
            <?php if($odTip): ?><input type="hidden" name="tip" value="<?= htmlspecialchars($odTip) ?>"><?php endif; ?>
            <input type="text" name="q" value="<?= htmlspecialchars($odSrch) ?>"
                placeholder="Sipariş no / ürün ara…"
                style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:7px 12px;border-radius:8px;font-size:12px;width:220px">
            <button type="submit" class="btn btn-primary" style="padding:7px 14px;font-size:12px">🔍</button>
            <?php if($odSrch||$odTip): ?>
            <a href="?action=odemeler&donem=<?= urlencode($odDonem) ?>" class="btn" style="padding:7px 12px;font-size:12px;border:1px solid var(--border)">✕ Sıfırla</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if(empty($siparisOzet)): ?>
    <div class="no-data"><div class="icon">🔍</div>Eşleşen sipariş bulunamadı.</div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table>
        <thead>
        <tr>
            <th>Sipariş No</th>
            <th>Ürün</th>
            <th>Sipariş Tarihi</th>
            <th>Vade Tarihi</th>
            <th style="text-align:right">Brüt Tutar</th>
            <th style="text-align:right">Net Hakediş</th>
            <th style="text-align:center">İşlem</th>
            <th style="text-align:center">Ödeme Durumu</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($siparisOzet as $sip):
            $vadeGun  = $sip['vade_tarihi'] ? substr($sip['vade_tarihi'],0,10) : null;
            $hasIade  = strpos($sip['tipler'], 'İade') !== false;
            $hasKupon = strpos($sip['tipler'], 'Kupon') !== false;

            if ($hasIade && abs((float)$sip['net_hakedis']) < 0.01) {
                $durum = 'iade'; $durumLabel = '↩ Tam İade'; $durumClass = 'badge-red';
            } elseif ($vadeGun && $vadeGun <= $today) {
                $durum = 'odendi'; $durumLabel = '✅ Ödendi'; $durumClass = 'badge-green';
            } else {
                $durum = 'bekle'; $durumLabel = '⏳ Bekliyor'; $durumClass = 'badge-yellow';
                if ($vadeGun) $durumLabel .= ' ('.$vadeGun.')';
            }

            // Ürün adını kısalt
            $urunKisa = mb_substr(strip_tags($sip['urunler']??''), 0, 60);
            if (mb_strlen($sip['urunler']??'') > 60) $urunKisa .= '…';
        ?>
        <tr>
            <td style="font-family:monospace;font-size:11px;white-space:nowrap">
                <?= htmlspecialchars($sip['siparis_no']) ?>
            </td>
            <td style="max-width:260px">
                <span title="<?= htmlspecialchars($sip['urunler']??'') ?>"><?= htmlspecialchars($urunKisa) ?></span>
                <?php if($hasKupon): ?><span class="badge badge-yellow" style="font-size:9px;margin-left:4px">Kupon</span><?php endif; ?>
                <?php if($hasIade):  ?><span class="badge badge-red"    style="font-size:9px;margin-left:4px">İade</span><?php endif; ?>
            </td>
            <td style="font-size:12px;white-space:nowrap;color:var(--text2)">
                <?= $sip['siparis_tarihi'] ? date('d.m.Y', strtotime($sip['siparis_tarihi'])) : '—' ?>
            </td>
            <td style="font-size:12px;white-space:nowrap;<?= $durum==='bekle'?'color:var(--yellow)':'' ?>">
                <?= $vadeGun ? date('d.m.Y', strtotime($vadeGun)) : '—' ?>
            </td>
            <td style="text-align:right;font-size:13px">
                <?= $sip['brut_tutar'] != 0 ? fmtTL($sip['brut_tutar']) : '—' ?>
            </td>
            <td style="text-align:right;font-weight:600;font-size:13px" class="<?= $sip['net_hakedis']>0?'positive':($sip['net_hakedis']<0?'negative':'neutral') ?>">
                <?= fmtTL($sip['net_hakedis']) ?>
                <?php
                // Mini bar
                $maxH = 435; // yaklaşık max hakediş
                $pct  = min(100, round(abs($sip['net_hakedis'])/$maxH*100));
                if ($sip['net_hakedis'] > 0): ?>
                <div class="progress-bar" style="height:3px;margin-top:3px;width:80px;display:inline-block;vertical-align:middle">
                    <div class="progress-fill green" style="width:<?= $pct ?>%"></div>
                </div>
                <?php endif; ?>
            </td>
            <td style="text-align:center">
                <?php $tipler = explode(',', $sip['tipler']); foreach(array_unique($tipler) as $tip): ?>
                <?php $tc = match($tip) { 'Satış'=>'badge-green','İade'=>'badge-red','Kupon'=>'badge-yellow',default=>'badge-gray' }; ?>
                <span class="badge <?= $tc ?>" style="font-size:9px"><?= htmlspecialchars($tip) ?></span>
                <?php endforeach; ?>
            </td>
            <td style="text-align:center">
                <span class="badge <?= $durumClass ?>"><?= $durumLabel ?></span>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- Sayfalama -->
    <?php if($odSayfa > 1): ?>
    <div style="display:flex;gap:6px;margin-top:14px;flex-wrap:wrap">
        <?php for($pi=1;$pi<=$odSayfa;$pi++): ?>
        <a href="?action=odemeler&donem=<?= urlencode($odDonem) ?>&tip=<?= urlencode($odTip) ?>&q=<?= urlencode($odSrch) ?>&p=<?= $pi ?>"
           class="tab-btn <?= $pi===$odPg?'active':'' ?>" style="padding:5px 12px;font-size:12px"><?= $pi ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Diğer kalemlerin tablosu (kargo, reklam, ceza vb.) -->
<?php
$digerWhereSql = "WHERE magaza_id=?";
$digerPrms     = [$magazaId];
if ($odDonem === '_bos') {
    $digerWhereSql .= " AND (donem_tagi='' OR donem_tagi IS NULL)";
} elseif ($odDonem) {
    $digerWhereSql .= " AND donem_tagi=?"; $digerPrms[] = $odDonem;
}
$digerWhereSql .= " AND islem_tipi NOT IN ('Satış','İade','Kupon')";
$digerKalemler = DB::rows("
    SELECT islem_tipi, urun_adi, satici_hakedis, islem_tarihi
    FROM odeme_detay
    $digerWhereSql
    ORDER BY satici_hakedis ASC
", $digerPrms);
?>
<?php if(!empty($digerKalemler)): ?>
<div class="card">
    <div class="card-title">🧾 Diğer Kesintiler & Giderler</div>
    <table>
        <thead><tr><th>Tür</th><th>Açıklama</th><th>İşlem Tarihi</th><th style="text-align:right">Tutar</th></tr></thead>
        <tbody>
        <?php foreach($digerKalemler as $dk):
            $tc = 'badge-red';
            if (strpos($dk['islem_tipi'],'Reklam') !== false) $tc = 'badge-orange';
            elseif (strpos($dk['islem_tipi'],'Kargo') !== false) $tc = 'badge-blue';
            elseif (strpos($dk['islem_tipi'],'Platform') !== false) $tc = 'badge-gray';
            elseif (strpos($dk['islem_tipi'],'Stopaj') !== false) $tc = 'badge-gray';
        ?>
        <tr>
            <td><span class="badge <?= $tc ?>"><?= htmlspecialchars($dk['islem_tipi']) ?></span></td>
            <td style="font-size:12px;color:var(--text2);max-width:400px"><?= htmlspecialchars(mb_substr($dk['urun_adi']??'',0,120)) ?></td>
            <td style="font-size:12px;color:var(--text2);white-space:nowrap">
                <?= $dk['islem_tarihi'] ? date('d.m.Y', strtotime($dk['islem_tarihi'])) : '—' ?>
            </td>
            <td class="<?= $dk['satici_hakedis']>=0?'positive':'negative' ?>" style="text-align:right;font-weight:600">
                <?= fmtTL($dk['satici_hakedis']) ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
        <tr>
            <td colspan="3" style="font-weight:700;text-align:right;padding-right:16px">Toplam</td>
            <td class="<?= array_sum(array_column($digerKalemler,'satici_hakedis'))>=0?'positive':'negative' ?>" style="text-align:right;font-weight:700">
                <?= fmtTL(array_sum(array_column($digerKalemler,'satici_hakedis'))) ?>
            </td>
        </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>

<?php endif; // odemeDetaySayisi > 0 ?>

<?php elseif ($action === 'talepler'): ?>
<?php
// Talepler (Claims) ve Müşteri Soruları sayfası
$claimSayisi    = 0;
$soruSayisi     = 0;
$cevaplanmamis  = 0;
try {
    $claimSayisi   = (int)DB::scalar("SELECT COUNT(*) FROM talepler WHERE magaza_id=?", [$magazaId]);
    $soruSayisi    = (int)DB::scalar("SELECT COUNT(*) FROM musteri_sorulari WHERE magaza_id=?", [$magazaId]);
    $cevaplanmamis = (int)DB::scalar("SELECT COUNT(*) FROM musteri_sorulari WHERE magaza_id=? AND cevap_durumu='Cevaplanmadı'", [$magazaId]);
} catch(Exception $e) {}
$subTab = $_GET['tab'] ?? 'talepler';
?>
<div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;flex-wrap:wrap">
    <h2 style="margin:0">↩️ Talepler &amp; Sorular</h2>
    <a href="?action=talepler&tab=talepler" class="tab-btn <?= $subTab==='talepler'?'active':'' ?>">İadeler / Talepler (<?= $claimSayisi ?>)</a>
    <a href="?action=talepler&tab=sorular"  class="tab-btn <?= $subTab==='sorular'?'active':'' ?>">
        Müşteri Soruları (<?= $soruSayisi ?>)
        <?php if ($cevaplanmamis > 0): ?><span style="background:#e74c3c;color:#fff;border-radius:99px;padding:1px 7px;font-size:11px;margin-left:4px"><?= $cevaplanmamis ?></span><?php endif; ?>
    </a>
</div>

<?php if ($subTab === 'talepler'): ?>
<div style="margin-bottom:14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <div>
        <label style="font-size:12px;color:var(--text2)">Başlangıç</label>
        <input type="date" id="claimStart" value="<?= date('Y-m-d', strtotime('-30 days')) ?>" style="border:1px solid var(--border);background:var(--bg2);color:var(--text);border-radius:6px;padding:5px 8px;font-size:13px">
    </div>
    <div>
        <label style="font-size:12px;color:var(--text2)">Bitiş</label>
        <input type="date" id="claimEnd" value="<?= date('Y-m-d') ?>" style="border:1px solid var(--border);background:var(--bg2);color:var(--text);border-radius:6px;padding:5px 8px;font-size:13px">
    </div>
    <button class="btn btn-primary btn-sm" onclick="syncClaims()">📡 API'den Senkronize Et</button>
    <span id="claimStatus" style="font-size:12px;color:var(--text2)"></span>
</div>

<?php
$claimRows = [];
try {
    $claimRows = DB::rows(
        "SELECT claim_id,siparis_no,barcode,urun_adi,talep_tipi,talep_statusu,
                talep_tarihi,iade_tutari,musteri,neden
         FROM talepler WHERE magaza_id=? ORDER BY talep_tarihi DESC LIMIT 200",
        [$magazaId]
    );
} catch(Exception $e) {}
?>

<?php if (empty($claimRows)): ?>
<div class="alert alert-warning">Henüz talep verisi yok. API'den senkronize edin veya tarih aralığını genişletin.</div>
<?php else: ?>
<div style="overflow-x:auto"><table>
<thead><tr>
    <th>Talep ID</th><th>Sipariş No</th><th>Barkod</th><th>Ürün</th>
    <th>Tip</th><th>Durum</th><th>Tarih</th><th style="text-align:right">İade Tutarı</th><th>Müşteri</th>
</tr></thead>
<tbody>
<?php foreach ($claimRows as $c): ?>
<tr>
    <td style="font-size:11px;color:var(--text2)"><?= htmlspecialchars($c['claim_id']) ?></td>
    <td style="font-size:12px"><?= htmlspecialchars($c['siparis_no']) ?></td>
    <td style="font-size:11px"><?= htmlspecialchars($c['barcode']) ?></td>
    <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px" title="<?= htmlspecialchars($c['urun_adi']) ?>"><?= htmlspecialchars($c['urun_adi']) ?></td>
    <td><span style="font-size:11px;background:rgba(52,152,219,.15);color:#3498db;border-radius:4px;padding:2px 7px"><?= htmlspecialchars($c['talep_tipi']) ?></span></td>
    <td><span style="font-size:11px;padding:2px 7px;border-radius:4px;<?= str_contains($c['talep_statusu'],'Onay')? 'background:rgba(46,204,113,.15);color:#2ecc71' : 'background:rgba(231,76,60,.12);color:#e74c3c' ?>"><?= htmlspecialchars($c['talep_statusu']) ?></span></td>
    <td style="font-size:12px;white-space:nowrap"><?= $c['talep_tarihi'] ? date('d.m.Y', strtotime($c['talep_tarihi'])) : '—' ?></td>
    <td style="text-align:right;font-size:13px;color:<?= $c['iade_tutari'] > 0 ? '#e74c3c' : 'var(--text2)' ?>;font-weight:600"><?= $c['iade_tutari'] > 0 ? number_format($c['iade_tutari'],2,',','.') . ' ₺' : '—' ?></td>
    <td style="font-size:12px"><?= htmlspecialchars($c['musteri']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php endif; ?>

<?php else: // sorular tab ?>
<div style="margin-bottom:14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <button class="btn btn-primary btn-sm" onclick="syncQuestions()">📡 Soruları Güncelle</button>
    <a href="?action=talepler&tab=sorular&durum=Cevaplanmadı" class="tab-btn <?= ($_GET['durum']??'')==='Cevaplanmadı'?'active':'' ?>" style="font-size:12px">
        Cevaplanmayanlar<?= $cevaplanmamis > 0 ? " ($cevaplanmamis)" : '' ?>
    </a>
    <a href="?action=talepler&tab=sorular" class="tab-btn <?= !isset($_GET['durum'])?'active':'' ?>" style="font-size:12px">Tümü</a>
    <span id="qStatus" style="font-size:12px;color:var(--text2)"></span>
</div>

<?php
$durum = $_GET['durum'] ?? '';
$qRows = [];
try {
    $qWhere = 'magaza_id=?';
    $qPrms  = [$magazaId];
    if ($durum) { $qWhere .= ' AND cevap_durumu=?'; $qPrms[] = $durum; }
    $qRows = DB::rows(
        "SELECT question_id,barcode,urun_adi,soru_metni,cevap_metni,soru_tarihi,cevap_durumu
         FROM musteri_sorulari WHERE $qWhere ORDER BY soru_tarihi DESC LIMIT 200",
        $qPrms
    );
} catch(Exception $e) {}
?>

<?php if (empty($qRows)): ?>
<div class="alert alert-warning">Henüz soru verisi yok. API'den güncelle butonuna basın.</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:10px">
<?php foreach ($qRows as $q): ?>
<div style="background:var(--bg2);border:1px solid var(--border);border-radius:8px;padding:14px 16px">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:8px">
        <div>
            <span style="font-size:11px;font-weight:600;color:var(--text2)"><?= htmlspecialchars($q['barcode']) ?></span>
            <span style="margin-left:8px;font-size:12px;color:var(--text2)"><?= htmlspecialchars($q['urun_adi']) ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
            <span style="font-size:11px;color:var(--text2)"><?= $q['soru_tarihi'] ? date('d.m.Y H:i', strtotime($q['soru_tarihi'])) : '' ?></span>
            <?php if ($q['cevap_durumu']==='Cevaplanmadı'): ?>
            <span style="background:rgba(231,76,60,.15);color:#e74c3c;border-radius:4px;padding:2px 8px;font-size:11px;font-weight:600">Cevaplanmadı</span>
            <?php else: ?>
            <span style="background:rgba(46,204,113,.15);color:#2ecc71;border-radius:4px;padding:2px 8px;font-size:11px;font-weight:600">✓ Cevaplandı</span>
            <?php endif; ?>
        </div>
    </div>
    <div style="font-size:13px;margin-bottom:8px"><strong>❓</strong> <?= nl2br(htmlspecialchars($q['soru_metni'])) ?></div>
    <?php if ($q['cevap_metni']): ?>
    <div style="font-size:13px;color:var(--text2);border-left:3px solid var(--primary);padding-left:10px"><strong>💬</strong> <?= nl2br(htmlspecialchars($q['cevap_metni'])) ?></div>
    <?php else: ?>
    <div style="margin-top:8px;display:flex;gap:6px">
        <textarea id="ans_<?= htmlspecialchars($q['question_id'],ENT_QUOTES) ?>" rows="2"
            style="flex:1;background:var(--bg3);border:1px solid var(--border);border-radius:6px;padding:6px 10px;color:var(--text);font-size:13px;resize:vertical"
            placeholder="Yanıtınızı yazın..."></textarea>
        <button class="btn btn-primary btn-sm" style="align-self:flex-end"
            onclick="answerQuestion('<?= htmlspecialchars($q['question_id'],ENT_QUOTES) ?>', this)">Gönder</button>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; // tab ?>

<script>
function syncClaims() {
    const s = document.getElementById('claimStart').value;
    const e = document.getElementById('claimEnd').value;
    document.getElementById('claimStatus').textContent = '⏳ Senkronize ediliyor…';
    fetch('ajax.php', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=sync_claims&start_date=${s}&end_date=${e}`})
    .then(r=>r.json()).then(d=>{
        if (d.error) { document.getElementById('claimStatus').textContent = '❌ ' + d.error; return; }
        document.getElementById('claimStatus').textContent =
            `✅ ${d.inserted} yeni, ${d.updated} güncellendi`;
        setTimeout(() => location.reload(), 1200);
    }).catch(()=>{ document.getElementById('claimStatus').textContent = '❌ Bağlantı hatası'; });
}
function syncQuestions() {
    document.getElementById('qStatus').textContent = '⏳ Sorular alınıyor…';
    fetch('ajax.php', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: 'action=sync_questions'})
    .then(r=>r.json()).then(d=>{
        if (d.error) { document.getElementById('qStatus').textContent = '❌ ' + d.error; return; }
        document.getElementById('qStatus').textContent =
            `✅ ${d.inserted} yeni, ${d.updated} güncellendi, ${d.unanswered} cevaplanmamış`;
        setTimeout(() => location.reload(), 1200);
    }).catch(()=>{ document.getElementById('qStatus').textContent = '❌ Bağlantı hatası'; });
}
function answerQuestion(qId, btn) {
    const ta = document.getElementById('ans_' + qId);
    const cevap = ta.value.trim();
    if (!cevap) { alert('Yanıt boş olamaz'); return; }
    btn.disabled = true; btn.textContent = '⏳';
    const fd = new URLSearchParams({action:'answer_question', question_id:qId, cevap});
    fetch('ajax.php', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:fd.toString()})
    .then(r=>r.json()).then(d=>{
        if (d.error) { alert('Hata: ' + d.error); btn.disabled=false; btn.textContent='Gönder'; return; }
        location.reload();
    });
}
</script>

<?php elseif ($action === 'ai_analiz'): ?>
<?php
$anthropicOk = !empty($magaza['anthropic_api_key'] ?? '') && strlen($magaza['anthropic_api_key']) > 20;
// Dönem: bu_ay | gecen_ay | son_3_ay (default: bu_ay)
$aiDonem = $_GET['donem'] ?? 'bu_ay';
if (!in_array($aiDonem, ['bu_ay','gecen_ay','son_3_ay'])) $aiDonem = 'bu_ay';

// Kayıtlı yorumları çek
$kayitliYorumlar = [];
try {
    $rows = DB::rows("SELECT tip, yorum, olusturuldu FROM ai_yorumlar WHERE magaza_id=? AND donem=? ORDER BY tip",
        [$magazaId, $aiDonem]);
    foreach ($rows as $r) $kayitliYorumlar[$r['tip']] = $r;
} catch(PDOException $e) {}
?>
<div class="page-title">🤖 <span>AI Analiz</span>
<span style="font-size:13px;color:var(--text2);font-weight:400;margin-left:10px">Claude tarafından analiz edildi</span>
</div>

<?php if (!$anthropicOk): ?>
<div class="card" style="text-align:center;padding:60px 40px">
    <div style="font-size:48px;margin-bottom:16px">🤖</div>
    <div style="font-size:17px;font-weight:600;margin-bottom:8px">Claude API Key Girilmemiş</div>
    <div style="color:var(--text2);margin-bottom:24px;font-size:13px">
        AI Analiz özelliğini kullanmak için Anthropic API key gereklidir.<br>
        <a href="https://console.anthropic.com/settings/keys" target="_blank" style="color:var(--primary)">console.anthropic.com</a>'dan alabilirsiniz.
    </div>
    <a href="?action=ayarlar" class="btn btn-primary" style="font-size:14px;padding:12px 28px">⚙️ Ayarlara Git</a>
</div>
<?php else: ?>

<!-- Dönem seçici -->
<div style="display:flex;gap:10px;align-items:center;margin-bottom:20px;flex-wrap:wrap">
    <div style="display:flex;gap:6px">
        <?php foreach(['bu_ay'=>'Bu Ay','gecen_ay'=>'Geçen Ay','son_3_ay'=>'Son 3 Ay'] as $dk=>$dl): ?>
        <a href="?action=ai_analiz&donem=<?= $dk ?>"
           class="tab-btn <?= $aiDonem===$dk?'active':'' ?>"
           style="font-size:13px;padding:7px 16px"><?= $dl ?></a>
        <?php endforeach; ?>
    </div>
    <button class="btn btn-primary" onclick="tumunuAnalizeEt()" style="margin-left:auto;font-size:13px;padding:8px 18px">
        ⚡ Tümünü Analiz Et
    </button>
</div>

<!-- Analiz Kartları -->
<div style="display:flex;flex-direction:column;gap:16px" id="aiKartlar">
<?php
$kartlar = [
    ['tip'=>'stratejik',  'baslik'=>'📊 Stratejik Analiz',       'aciklama'=>'Net kar, ciro, maliyet ve marj trendleri'],
    ['tip'=>'kategori',   'baslik'=>'🏷️ Kategori Analizi',        'aciklama'=>'Kategorilere göre karlılık ve büyüme'],
    ['tip'=>'urun',       'baslik'=>'📦 Ürün Portföyü Analizi',   'aciklama'=>'En iyi/kötü performanslı ürünler, konsantrasyon riski'],
    ['tip'=>'odeme',      'baslik'=>'💰 Ödeme & Hakediş Analizi', 'aciklama'=>'Kargo/platform yükü, bekleyen ödemeler'],
    ['tip'=>'operasyonel','baslik'=>'⚙️ Operasyonel Analiz',      'aciklama'=>'İade oranları, eksik maliyetler, reklam verimliliği'],
];
foreach ($kartlar as $k):
?>
<div class="card ai-kart" id="kart-<?= $k['tip'] ?>">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <div>
            <div style="font-size:15px;font-weight:600"><?= $k['baslik'] ?></div>
            <div style="font-size:12px;color:var(--text2);margin-top:2px"><?= $k['aciklama'] ?></div>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
            <?php if (isset($kayitliYorumlar[$k['tip']])): ?>
            <span style="font-size:10px;color:var(--text2)">
                Son: <?= date('d.m.Y H:i', strtotime($kayitliYorumlar[$k['tip']]['olusturuldu'])) ?>
            </span>
            <?php endif; ?>
            <button class="btn btn-primary" onclick="analizeEt('<?= $k['tip'] ?>')"
                    id="btn-<?= $k['tip'] ?>" style="font-size:12px;white-space:nowrap">
                <?= isset($kayitliYorumlar[$k['tip']]) ? '🔄 Yenile' : '🤖 Analiz Et' ?>
            </button>
        </div>
    </div>
    <div id="sonuc-<?= $k['tip'] ?>"
         style="<?= isset($kayitliYorumlar[$k['tip']]) ? '' : 'display:none;' ?>margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
        <div class="ai-loading" style="display:none;color:var(--text2);font-size:13px;padding:8px 0">
            <span class="ai-spinner">⟳</span> Claude analiz ediyor...
        </div>
        <div class="ai-metin" style="font-size:13px;line-height:1.9;color:var(--text)"><?php
        if (isset($kayitliYorumlar[$k['tip']])) {
            $ky = htmlspecialchars($kayitliYorumlar[$k['tip']]['yorum'], ENT_NOQUOTES);
            echo '<div class="ai-kayitli-yorum" data-raw="'.htmlspecialchars($kayitliYorumlar[$k['tip']]['yorum'], ENT_QUOTES).'"></div>';
        }
        ?></div>
    </div>
</div>
<?php endforeach; ?>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
.ai-spinner { display:inline-block; animation: spin 1s linear infinite; }
.ai-metin p { margin:0 0 10px;line-height:1.9; }
</style>

<script>
var AI_DONEM = '<?= $aiDonem ?>';

// Sayfa yüklenince kayıtlı yorumları render et
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.ai-kayitli-yorum').forEach(function(el) {
        el.parentElement.innerHTML = renderAiMetin(el.getAttribute('data-raw'));
    });
});

function renderAiMetin(text) {
    if (!text) return '';
    text = text.replace(/^#{1,3}\s*/gm, '');
    text = text.replace(/\*\*([^*]+)\*\*/g, '$1');
    text = text.replace(/\*([^*]+)\*/g, '$1');
    text = text.replace(/^[-•]\s*/gm, '');
    var lines = text.split('\n').filter(function(l){ return l.trim(); });
    var html = '';
    lines.forEach(function(line) {
        var t = line.trim();
        if (!t) return;
        var s = '';
        if (t.startsWith('🔴'))      s = 'border-left:3px solid var(--red);padding-left:12px;';
        else if (t.startsWith('🟡')) s = 'border-left:3px solid var(--yellow);padding-left:12px;';
        else if (t.startsWith('🟢')) s = 'border-left:3px solid var(--green);padding-left:12px;';
        html += '<p style="margin:0 0 10px;line-height:1.9;'+s+'">' + t + '</p>';
    });
    return html || '<p style="color:var(--text2)">Analiz tamamlandı.</p>';
}

function analizeEt(tip) {
    var btn     = document.getElementById('btn-'+tip);
    var sonuc   = document.getElementById('sonuc-'+tip);
    var loading = sonuc.querySelector('.ai-loading');
    var metin   = sonuc.querySelector('.ai-metin');

    btn.disabled = true;
    btn.textContent = '⏳ Analiz ediliyor...';
    sonuc.style.display = 'block';
    loading.style.display = 'block';
    metin.innerHTML = '';

    post({ action:'ai_analiz', tip:tip, donem:AI_DONEM })
    .then(function(d) {
        btn.disabled = false;
        btn.textContent = '🔄 Yenile';
        loading.style.display = 'none';
        if (d.error) {
            metin.innerHTML = '<div style="color:var(--red);padding:8px 0">❌ ' + d.error + '</div>';
            return;
        }
        metin.innerHTML = renderAiMetin(d.analiz||'');
    })
    .catch(function() {
        btn.disabled = false;
        btn.textContent = '🤖 Analiz Et';
        loading.style.display = 'none';
        metin.innerHTML = '<div style="color:var(--red)">❌ Bağlantı hatası</div>';
    });
}

function tumunuAnalizeEt() {
    ['stratejik','kategori','urun','odeme','operasyonel'].forEach(function(tip, i) {
        setTimeout(function(){ analizeEt(tip); }, i * 1500);
    });
}
</script>

<?php endif; ?>

<?php elseif ($action === 'ayarlar'): ?>
<?php
// Ayarlar kaydet
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['form']??'')==='api_ayarlar') {
    $ad  = trim($_POST['magaza_adi']   ?? '');
    $sid = trim($_POST['ty_seller_id'] ?? '');
    $key = trim($_POST['ty_api_key']   ?? '');
    $sec = trim($_POST['ty_api_secret']?? '');
    $ant = trim($_POST['anthropic_api_key'] ?? '');
    if ($ad) {
        DB::exec("UPDATE magazalar SET magaza_adi=?,ty_seller_id=?,ty_api_key=?,ty_api_secret=?,anthropic_api_key=? WHERE id=?",
            [$ad, $sid, $key ?: $magaza['ty_api_key'], $sec ?: $magaza['ty_api_secret'],
             $ant ?: ($magaza['anthropic_api_key'] ?? ''), $magazaId]);
        $updMag = DB::row("SELECT * FROM magazalar WHERE id=?", [$magazaId]);
        $_SESSION['magaza'] = $updMag;
        $magaza = $updMag;
        $message = "✅ API bilgileri kaydedildi.";
        $api   = new TrendyolApi($magaza);
        $apiOk = $api->isConfigured();
    }
}
// Magaza'yı yenile (kayıt sonrası güncellendi olabilir)
$magaza = DB::row("SELECT * FROM magazalar WHERE id=?", [$magazaId]);
$anthropicOk = !empty($magaza['anthropic_api_key'] ?? '') && strlen($magaza['anthropic_api_key']) > 20;
?>
<div class="page-title">⚙️ <span>Ayarlar / API Yapılandırması</span></div>
<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<div class="grid-2">
<div class="card">
    <div class="card-title">🔌 Trendyol API Bilgileri</div>
    <form method="POST">
        <input type="hidden" name="form" value="api_ayarlar">
        <div class="form-grid">
            <div class="form-group" style="grid-column:1/-1">
                <label>Mağaza Adı</label>
                <input type="text" name="magaza_adi" value="<?= htmlspecialchars($magaza['magaza_adi']) ?>" required>
            </div>
            <div class="form-group">
                <label>Seller ID (Satıcı ID) *</label>
                <input type="text" name="ty_seller_id" value="<?= htmlspecialchars($magaza['ty_seller_id']??'') ?>" placeholder="Ör: 504165">
            </div>
            <div class="form-group">
                <label>API Key *</label>
                <input type="text" name="ty_api_key" value="<?= htmlspecialchars($magaza['ty_api_key']??'') ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label>API Secret *</label>
                <input type="password" name="ty_api_secret" placeholder="Mevcut şifreyi değiştirmek için girin">
                <?php if ($magaza['ty_api_secret']): ?><small style="color:var(--green)">✓ Kayıtlı (değiştirmek için yeni değer girin)</small><?php endif; ?>
            </div>
            <div class="form-group" style="grid-column:1/-1;border-top:1px solid var(--border);padding-top:14px;margin-top:4px">
                <label>🤖 Anthropic (Claude) API Key <span style="font-size:11px;color:var(--text2);font-weight:400">— AI Analiz sayfası için</span></label>
                <input type="password" name="anthropic_api_key" placeholder="sk-ant-... (değiştirmek için girin)">
                <?php if ($anthropicOk): ?>
                    <small style="color:var(--green)">✓ Claude API bağlı — AI Analiz aktif (değiştirmek için yeni key girin)</small>
                <?php else: ?>
                    <small style="color:var(--text2)">Alın: <a href="https://console.anthropic.com/settings/keys" target="_blank" style="color:var(--primary)">console.anthropic.com</a> · Key girilmemiş</small>
                <?php endif; ?>
            </div>
        </div>
        <div style="margin-top:8px;font-size:12px;color:var(--text2);line-height:1.8;margin-bottom:14px">
            Trendyol Satıcı Paneli → <strong>Hesap Bilgilerim</strong> → <strong>Entegrasyon Bilgileri</strong>'nden alınır.
        </div>
        <button type="submit" class="btn btn-primary">💾 Kaydet</button>
        <?php if ($apiOk): ?><span style="margin-left:10px"><span class="api-badge ok">● Trendyol API Bağlı</span></span><?php else: ?><span style="margin-left:10px"><span class="api-badge fail">● Trendyol Yapılandırılmamış</span></span><?php endif; ?>
        <?php if ($anthropicOk): ?><span style="margin-left:8px"><span class="api-badge ok">● Claude AI Aktif</span></span><?php endif; ?>
    </form>
    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
        <div class="stat-row"><span>API Ürün Sayısı</span><span style="font-weight:600"><?= fmt($stats['ty_urun_sayisi'],0) ?></span></div>
        <div class="stat-row"><span>Son Senkronizasyon</span><span style="font-size:12px;color:var(--text2)"><?= DB::scalar("SELECT MAX(cekme_tarihi) FROM trendyol_urunler WHERE magaza_id=?",[$magazaId]) ?: '—' ?></span></div>
    </div>
</div>
    <div class="card-title">🗄️ Veritabanı & Hesap</div>
    <div class="stat-row"><span>Kullanıcı</span><span style="font-size:12px"><?= htmlspecialchars($authUser['email']) ?></span></div>
    <div class="stat-row"><span>Rol</span><span><span class="badge <?= isAdmin()?'badge-orange':'badge-blue' ?>"><?= isAdmin()?'Admin':'Üye' ?></span></span></div>
    <div class="stat-row"><span>DB Host</span><span style="font-family:monospace;font-size:12px"><?= DB_HOST ?></span></div>
    <div class="stat-row"><span>Veritabanı</span><span style="font-family:monospace;font-size:12px"><?= DB_NAME ?></span></div>
    <div class="stat-row"><span>Siparişler</span><span style="font-weight:600"><?= fmt($stats['toplam_siparis'],0) ?></span></div>
    <div class="stat-row"><span>Satış Ürünleri</span><span style="font-weight:600"><?= fmt($stats['urun_satis_sayisi'],0) ?></span></div>
    <div class="stat-row"><span>Maliyetler</span><span style="font-weight:600"><?= fmt($stats['maliyet_sayisi'],0) ?></span></div>
    <?php if (isAdmin()): ?>
    <div style="margin-top:14px">
        <a href="admin.php" class="btn btn-primary" style="width:100%;text-align:center;display:block">⚙️ Admin Paneline Git</a>
    </div>
    <?php endif; ?>
</div>
</div>
<?php endif; ?>
</div>

<script>
function secimiGuncelle() {
    var secili = document.querySelectorAll('.sipChk:checked').length;
    var sayac  = document.getElementById('seciliSayac');
    var btn    = document.getElementById('topluSilBtn');
    if (sayac) sayac.textContent = secili + ' sipariş seçili';
    if (btn)   btn.disabled = secili === 0;
}

function tumunuSec(sec) {
    document.querySelectorAll('.sipChk').forEach(function(c){ c.checked = sec; });
    var chkAll = document.getElementById('chkAll');
    if (chkAll) chkAll.checked = sec;
    secimiGuncelle();
}

function topluSil() {
    var idler = Array.from(document.querySelectorAll('.sipChk:checked')).map(function(c){ return c.value; });
    if (idler.length === 0) return;
    if (!confirm(idler.length + ' sipariş silinecek. Bu işlem geri alınamaz. Devam?')) return;
    var btn = document.getElementById('topluSilBtn');
    btn.disabled = true;
    btn.textContent = '⏳ Siliniyor...';
    fetch(AJAX_URL, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=delete_siparis_bulk&ids=' + encodeURIComponent(idler.join(','))
    }).then(function(r){ return r.json(); })
    .then(function(d) {
        if (d.ok) {
            idler.forEach(function(id) {
                var tr = document.querySelector('tr[data-id="' + id + '"]');
                if (tr) tr.remove();
            });
            var chk = document.getElementById('chkAll');
            if (chk) chk.checked = false;
            secimiGuncelle();
            if (typeof toast === 'function') toast('✅ ' + d.deleted + ' sipariş silindi');
        } else {
            alert('Hata: ' + (d.error || 'Bilinmeyen'));
        }
        btn.disabled = false;
        btn.textContent = '🗑 Seçilenleri Sil';
    })
    .catch(function(e) {
        alert('Hata: ' + e.message);
        btn.disabled = false;
        btn.textContent = '🗑 Seçilenleri Sil';
    });
}

function toast(msg, ok=true) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.display = 'block';
    t.style.borderColor = ok ? 'var(--green)' : 'var(--red)';
    t.style.color = ok ? 'var(--green)' : 'var(--red)';
    setTimeout(() => t.style.display = 'none', 3500);
}

var AJAX_URL = '<?= htmlspecialchars(rtrim(dirname($_SERVER["PHP_SELF"]),"/") . "/ajax.php") ?>';
function post(data) {
    return fetch(AJAX_URL, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: Object.entries(data).map(([k,v])=>encodeURIComponent(k)+'='+encodeURIComponent(v)).join('&')
    }).then(r => r.json());
}

function syncProducts() {
    const btn = event.target;
    btn.disabled = true; btn.textContent = '⏳ Senkronize ediliyor...';
    post({action:'sync_products'}).then(d => {
        if (d.error) { toast('❌ '+d.error, false); }
        else { toast(`✅ ${d.inserted} eklendi, ${d.updated} güncellendi (Toplam: ${d.total})`); setTimeout(()=>location.reload(),2000); }
        btn.disabled = false; btn.textContent = '🔄 API\'den Senkronize Et';
    }).catch(e => { toast('❌ Bağlantı hatası', false); btn.disabled=false; });
}

function normalizeStatus() {
    post({action:'normalize_status'}).then(d => {
        toast(`✅ ${d.updated||0} sipariş statüsü Türkçeye çevrildi`);
        setTimeout(()=>location.reload(),1000);
    });
}

function syncOrders() {
    const start = document.getElementById('syncStart').value;
    const end   = document.getElementById('syncEnd').value;
    if (!start || !end) { toast('Lütfen tarih aralığı girin', false); return; }
    const diff = Math.ceil((new Date(end) - new Date(start)) / (1000*60*60*24));
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = diff > 14 ? `⏳ ${Math.ceil(diff/13)} dilim çekiliyor...` : '⏳ Çekiliyor...';
    post({action:'sync_orders', start_date:start, end_date:end}).then(d => {
        if (d.error) { toast('❌ ' + d.error, false); }
        else { toast(`✅ ${d.synced} sipariş güncellendi, ${d.lines_saved} kalem eklendi, ${d.matched} eşleştirme yapıldı`); setTimeout(()=>location.reload(),1500); }
        btn.disabled = false; btn.textContent = '📡 Sipariş Verilerini Çek';
    }).catch(()=>{ toast('❌ Bağlantı hatası', false); btn.disabled=false; });
}

function rematch() {
    const btns = document.querySelectorAll('[onclick="rematch()"]');
    btns.forEach(b => { b.disabled=true; b.textContent='⏳ Eşleştiriliyor...'; });
    post({action:'rematch'}).then(d => {
        toast(`✅ ${d.matched?.urun_satis||0} ürün, ${d.matched?.siparisler||0} sipariş eşleştirildi`);
        setTimeout(()=>location.reload(),1500);
    });
}

function assignOrder(sel, siparisNo) {
    const tyId = sel.value;
    post({action:'assign_order', siparis_no:siparisNo, ty_urun_id:tyId}).then(d => {
        if (d.ok) { toast(tyId ? '✅ Ürün atandı' : '🗑 Eşleştirme kaldırıldı'); setTimeout(()=>location.reload(),800); }
        else toast('❌ Hata', false);
    });
}

function clearAssign(siparisNo, el) {
    post({action:'assign_order', siparis_no:siparisNo, ty_urun_id:''}).then(d => {
        if (d.ok) { toast('🗑 Eşleştirme temizlendi'); setTimeout(()=>location.reload(),500); }
    });
}

function openCostModal(tyId, barcode, title) {
    document.getElementById('m_ty_id').value = tyId;
    document.getElementById('m_barcode').value = barcode;
    document.getElementById('m_urun_adi').textContent = title;
    document.getElementById('m_birim').value = '';
    document.getElementById('m_kargo').value = '0';
    document.getElementById('m_paket').value = '0';
    document.getElementById('m_diger').value = '0';
    document.getElementById('costModal').style.display = 'flex';
}

function saveCost() {
    post({action:'save_cost',
        ty_urun_id: document.getElementById('m_ty_id').value,
        barcode:    document.getElementById('m_barcode').value,
        urun_adi:   document.getElementById('m_urun_adi').textContent,
        birim_maliyet: document.getElementById('m_birim').value,
        kargo_maliyeti: document.getElementById('m_kargo').value,
        paket_maliyeti: document.getElementById('m_paket').value,
        diger_maliyet: document.getElementById('m_diger').value,
    }).then(d => {
        document.getElementById('costModal').style.display = 'none';
        if (d.ok) { toast('✅ Maliyet kaydedildi'); setTimeout(()=>location.reload(),800); }
        else toast('❌ '+d.error, false);
    });
}

function deleteCost(id, el) {
    if (!confirm('Bu maliyeti sil?')) return;
    post({action:'delete_cost', id}).then(d => {
        if (d.ok) { toast('🗑 Silindi'); setTimeout(()=>location.reload(),600); }
    });
}

function clearTable(tbl) {
    if (!confirm(tbl + ' tablosunu tamamen temizle?')) return;
    post({action:'clear_table', table:tbl}).then(d => {
        if (d.ok) { toast('✅ Temizlendi'); setTimeout(()=>location.reload(),800); }
    });
}

function selectCostProduct(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('cm_title').value = opt.dataset.title || '';
}

function saveCostFromForm() {
    const sel   = document.getElementById('cm_select');
    const tyId  = sel.value;
    const opt   = sel.options[sel.selectedIndex];
    if (!tyId) { toast('Lütfen bir ürün seçin', false); return; }
    post({action:'save_cost',
        ty_urun_id: tyId,
        barcode:    opt.dataset.barcode||'',
        urun_adi:   opt.dataset.title||'',
        birim_maliyet: document.getElementById('cm_birim').value,
        kargo_maliyeti: document.getElementById('cm_kargo').value,
        paket_maliyeti: document.getElementById('cm_paket').value,
        diger_maliyet:  document.getElementById('cm_diger').value,
    }).then(d => {
        if (d.ok) { toast('✅ Maliyet kaydedildi'); setTimeout(()=>location.reload(),800); }
        else toast('❌ '+d.error, false);
    });
}
</script>
</body>
</html>