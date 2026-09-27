plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
    id("kotlin-parcelize")
}

android {
    namespace = "com.techybugs.gymatlas.smarthub"
    compileSdk = 36

    defaultConfig {
        applicationId = "com.techybugs.gymatlas.smarthub"
        minSdk = 26
        targetSdk = 36
        versionCode = 4
        versionName = "0.2.2"
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }
}

dependencies {
    testImplementation("junit:junit:4.13.2")
    testImplementation("org.json:json:20240303")
}
