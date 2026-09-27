# Gym Atlas Smart Attendance BLE V2 protocol

V2 keeps the V1 service UUID and security boundary while reducing the scan-response payload enough for Android legacy BLE advertising.

## Advertisement

- Service UUID: `8b0f9c60-4f6d-4b40-9e8d-2d5d3f73a1a1`.
- Primary advertisement: non-connectable Atlas service UUID.
- Scan response: service data for the same UUID.
- Service-data byte `0`: protocol version `0x02`.
- Service-data bytes `1..9`: the 13-character suffix of the public ID packed as one unsigned big-endian base-36 integer.

The backend public ID format is `SAH` plus exactly 13 uppercase ASCII letters or digits. The `SAH` prefix is implicit in V2 and restored by the Member parser. Base-36 digits use `0-9` for values 0-9 and `A-Z` for values 10-35. The packed value is left-padded to exactly nine bytes.

Example public ID `SAHABC123DEF4567` becomes:

```text
02 02 A6 49 21 B5 36 6C EA 2F
```

The 10-byte value plus the 128-bit service-data UUID fits within the 31-byte legacy scan-response limit. Clients must reject V2 values that are not exactly nine packed bytes or that exceed 13 base-36 digits. Member clients accept V1 and V2 during migration; current Hub builds transmit V2.

Only the public lookup identifier is advertised. Hub UUIDs, secrets, gym data, member data, and API tokens must never enter the packet. Attendance authorization remains server-side.
