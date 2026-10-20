# Something does not work: what do I check first?

Most problems have one of four causes: your rights, the site's provider setup, a usage limit, or the background job
queue.

## Who can do this

Editors check the first steps themselves; the rest needs an administrator.

## Steps

1. **Read the message.** It names the cause, for example "You do not have access to Exponential Intelligence.", "The
   limit ... is reached", or "... did not answer in time".
2. **Check your rights.** The Intelligence tab and the dashboard need `expai/write`; Vector, chains and translations
   need their own policies: see [Who may use it, and why don't I see it?](who-may-use-it.md)
3. **Check the limits.** **Dashboard** > **Exponential Intelligence** > **Limits** shows what is used and when it
   resets.
4. **Reload the page.** "The form token is missing or wrong" means the page was open too long.
5. **Ask an administrator** with the time, the page and the message. They find the request in **EI: Log** and in the
   audit trail (channel `ai`), and the fixes in the troubleshooting page.

## What can go wrong

- **Queued work never starts** (chains, alt text suggestions, background runs stay "queued"): the job queue worker or
  its cronjob does not run.
- **The provider refuses or does not answer:** the provider's key, region or service has a problem; only an
  administrator can fix that.

## Related pages

- [Troubleshooting](../troubleshooting.md)
- [Frequently asked questions](../faq.md)
- [Audit trail](../audit-trail.md)
- [User manual](README.md)
