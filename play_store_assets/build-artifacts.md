# Final Android release artifacts

Member release built and verified on 22 August 2026. Trainer release last
verified on 28 July 2026.

## Atlas Member

- Version: `1.0.2+12`
- Package: `com.techybugs.gymatlas.member`
- AAB:
  `flutter_member_app/build/app/outputs/bundle/release/app-release.aab`
- Size: `48,751,451 bytes` (`48.8 MB` as reported by Flutter)
- SHA-256:
  `517955ff2bf9d544b5c981f53f85752bf3f2a911363f7bbca7b58d50f5aabfe6`
- JAR signature verification: `Passed`
- Upload-key alias: `atlas-member-upload`
- Upload certificate SHA-1:
  `CD:D3:FC:3A:3A:5D:74:B9:E6:FC:36:48:CF:AF:08:AB:AF:1C:C1:54`
- Upload certificate SHA-256:
  `AD:C5:B6:44:9E:5E:A9:72:54:75:30:54:5F:AA:B6:79:8B:0A:F8:D0:09:6A:EB:73:45:46:DA:5C:E6:DB:57:4A`

## Atlas Trainer

- Version: `1.0.0+1`
- Package: `com.techybugs.gymatlas.trainer`
- AAB:
  `flutter_trainer_app/build/app/outputs/bundle/release/app-release.aab`
- Size: `46.6 MB`
- SHA-256:
  `b8c94a53278492a7ff9b1991f7499ff4d2028c848603382fb074f614ca5c95a1`
- JAR signature verification: `Passed`
- Upload-key alias: `atlas-trainer-upload`
- Upload certificate SHA-1:
  `07:01:D0:39:9A:6A:B6:2B:18:13:A9:2E:C2:5D:85:DB:DE:51:BD:39`
- Upload certificate SHA-256:
  `6F:B6:D6:1C:A6:BD:9A:5B:0D:CB:90:A9:82:26:B2:21:0A:F5:70:F9:FC:4D:96:0E:EC:04:DA:BF:FA:ED:A1:1E`

The self-signed upload-certificate warning from `jarsigner` is expected for an
Android upload key. Google Play App Signing supplies the distribution signing
certificate.

## Store image checksums

- Play icon:
  `3e3f18d29d837023b2bdd5885ae31db4ca3a1ad957dbcd8449d4342977f0b5c3`
- Member feature graphic:
  `c13a00bc70769e83f4c8a707432058b8f944096bf4fda338d115cc73f1365ec6`
- Trainer feature graphic:
  `41a3dd51406c517890bd0e23a0b06e9d967d9db3e61bdc7b905f201fa1829634`

The icon is 512 × 512, both feature graphics are 1024 × 500 without alpha, and
all eight selected generated phone visuals are 1080 × 1920 without alpha.
