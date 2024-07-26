# Moodle Plugin LTI Submissions

Moodle assignment plugin that uses LTI AGS to accept submission PDFs from an external LTI 1.3 tool like Cadmus.


## Documentation

> **PLEASE NOTE**
> 
> The LTI integration documentation linked in the production is for production use. The development use case should include the development LTI 1.3 integration settings below.

Plugin Documentation published here: [Cadmus Moodle LTI Plugin Documentation](https://cadmusio.notion.site/Moodle-LTI-Plugin-7286c11664fe4632837a6eebddab49e6?pvs=74)

### Development LTI 1.3 Integration

Development LTI 1.3 Integration is similar to the production integration in [Cadmus LTI 1.3 Moodle Integration](https://support.cadmus.io/integrations/moodle), except that the domain used should be `api-staging.cadmus.io` instead of `api.cadmus.io`. 

  * **Tool name:**

        Cadmus LTI 1.3
    
  * **Tool URL**
    
        https://api-staging.cadmus.io/accounts/lti1p3/launch
    
  * **LTI version:** LTI 1.3
    
  * **Public key type:** Keyset URL
    
  * **Public keyset:**

        https://api-staging.cadmus.io/accounts/lti1p3/jwks
    
  * **Initiate login URL:**

        https://api-staging.cadmus.io/accounts/lti1p3/login
    
  * **Redirect URI:**

        https://api-staging.cadmus.io/accounts/lti1p3/launch

## Motivation

The [MOTIVATION.md](MOTIVATION.md) file outlines our initial design idea for a Moodle integration. 
