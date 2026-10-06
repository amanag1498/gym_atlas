# Final Android release artifacts

Member and Trainer Android release bundles were rebuilt on 2 October 2026.
Matching iOS IPAs were also exported locally. Store upload and review are
separate steps.

## Gym Atlas

- Version: `1.0.9+20`
- Package: `com.techybugs.gymatlas.member`
- AAB:
  `flutter_member_app/build/app/outputs/bundle/release/app-release.aab`
- Size: `50.4 MB` as reported by Flutter
- SHA-256: `ff0e04158651e35ded278b7c0bc68fb31f2b81c9f089f4fd5d3dee627930729c`
- IPA: `flutter_member_app/build/ios/ipa/Gym Atlas.ipa`
- IPA SHA-256: `b74ca7d953229d40accdc35b2cd177a4343874f048da7e4027a07da5f6de1988`
- JAR signature verification: `Passed`
- Upload-key alias: `atlas-member-upload`
- Upload certificate SHA-1:
  `CD:D3:FC:3A:3A:5D:74:B9:E6:FC:36:48:CF:AF:08:AB:AF:1C:C1:54`
- Upload certificate SHA-256:
  `AD:C5:B6:44:9E:5E:A9:72:54:75:30:54:5F:AA:B6:79:8B:0A:F8:D0:09:6A:EB:73:45:46:DA:5C:E6:DB:57:4A`

## Gym Atlas Coach

- Version: `1.0.6+16`
- Package: `com.techybugs.gymatlas.trainer`
- AAB:
  `flutter_trainer_app/build/app/outputs/bundle/release/app-release.aab`
- Size: `48.6 MB` as reported by Flutter
- SHA-256: `5bacf15fafb3f33b3f99f1dab375da3664889ecc6cdeea531c2130dd6e60fa7e`
- IPA: `flutter_trainer_app/build/ios/ipa/Gym Atlas Coach.ipa`
- IPA SHA-256: `760bca62e85de393f6dc92a8cf70fe7f6667c78b9c98764248eed420421faf47`
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
  `0f4f082465d358bf311a0ff096971d6a1820ff3a705dffbadd6d6d673185bcbe`
- Member feature graphic:
  `3bb5d67dc467738875c7d79c3f9bda24356183524c669ce470779456954647c6`
- Trainer feature graphic:
  `c45a33f061c2204d78a96a4fcafa232c731fe5275cbe7c8119cc79d953851c35`

The icon is 512 × 512 RGBA, both feature graphics are 1024 × 500 without alpha, and
the eight older generated phone visuals are 1080 × 1920 without alpha. Those
visuals still show the blue `A` and need refreshing before a `G` branded listing.
