# Final Google Play upload runbook

Complete the following in order for each app.

## 1. External prerequisites

1. Create and verify `support@gymatlas.in`.
2. Create two stable Google reviewer accounts, one member and one trainer.
3. In the Atlas admin/backend, give the member reviewer an active membership,
   assigned trainer, plans and sample data. Give the trainer reviewer assigned
   members, plans, follow-ups and chat data.
4. Deploy the backend changes and verify these URLs in an incognito browser:
   - `https://gymatlas.in/privacy-policy`
   - `https://gymatlas.in/contact`
   - `https://gymatlas.in/account-deletion?app=member`
   - `https://gymatlas.in/account-deletion?app=trainer`
5. Add each upload-key SHA-1 and SHA-256 to its matching Firebase Android app,
   download fresh `google-services.json` files, rebuild, and confirm Google
   sign-in from signed internal-test installs.
6. Back up both ignored JKS files, `key.properties` files and passwords in a
   secure organizational vault.

## 2. Create apps

In Play Console, create two apps using the exact setup values in:

- `member/play-console-fields.md`
- `trainer/play-console-fields.md`

Accept the declarations, enroll in Play App Signing, and keep package names
exactly as documented. Package names cannot be changed after the first artifact
is uploaded.

## 3. Complete Store presence

For each app:

1. Paste the name, short description and full description.
2. Set category and tags.
3. Enter the support email, website and privacy-policy URL.
4. Upload the common 512 × 512 icon.
5. Upload the app-specific 1024 × 500 feature graphic.
6. Upload the four screenshots in numbered order and add the supplied alt text.
7. Save the main store listing.

## 4. Complete Policy and programs > App content

Finish every card:

1. Privacy policy
2. Ads
3. App access
4. Target audience and content
5. Content ratings
6. Data safety
7. Health apps
8. Financial features
9. Account deletion
10. Any additional declaration Play Console displays

Use `app-content-answers.md` and `data-safety.md`. Do not submit until the App
content overview shows no incomplete required items.

## 5. Internal testing

1. Create an internal testing release.
2. Upload the corresponding signed `.aab`.
3. Use release name and notes from the app field sheet.
4. Resolve every Play pre-review error. Review warnings individually.
5. Add publisher/tester Google accounts and roll out the internal release.
6. Install from the Play opt-in link, not with `adb`.
7. Test Google sign-in, cold start, logout/login, notifications, chat opens,
   media selection, public policy/deletion links and offline launch.
8. Member only: test Health Connect grant/deny/revoke and nearby-gym location
   grant/deny.

If the developer account is a personal account created after 13 November 2023,
complete the currently required closed-test eligibility period before applying
for production access.

## 6. Production release

1. Open Production > Create new release.
2. Select the already tested bundle from the library.
3. Paste the release notes.
4. Choose India for the first rollout.
5. Keep Managed publishing on.
6. Start with a staged rollout (recommended: 10%).
7. Send for review.
8. After approval, publish through Managed publishing.
9. Monitor Android vitals, crashes/ANRs, reviews, sign-in and backend logs.
10. Expand to 50%, then 100%, only after the release is healthy.

## 7. Preserve release artifacts

Archive together:

- exact uploaded AAB and SHA-256 checksum
- git commit
- version name and version code
- upload certificate fingerprints
- mapping/native debug symbols if generated
- Dart split-debug-info directory if obfuscation was used
- final listing text and screenshots
- Play review decision and rollout date

Never delete the upload keys or obfuscation symbols for a published version.
