const b=document.getElementById("public-booking-root"),$e={first_name:"First name",last_name:"Last name",email:"Email address",phone:"Phone number",address:"Address",notes:"Notes"};b?we():console.warn("[public-booking] Root element not found.");function we(){const p=ve(),h=new Date().toISOString().slice(0,10);let u={view:"book",booking:B(p,h),manage:U(p,h),csrf:{header:b.dataset.csrfHeader||"X-CSRF-TOKEN",value:b.dataset.csrfValue||"",name:b.dataset.csrfName||"csrf_token"}};P(),u.booking.providerId&&u.booking.serviceId&&y("booking");function $(e){const t=ke();u=typeof e=="function"?e(he(u)):{...u,...e},P(),ye(t)}function E(e){$(t=>({...t,booking:typeof e=="function"?e(t.booking):{...t.booking,...e}}))}function v(e){$(t=>({...t,manage:typeof e=="function"?e(t.manage):{...t.manage,...e}}))}function S(e){$(t=>({...t,manage:{...t.manage,formState:typeof e=="function"?e(t.manage.formState):{...t.manage.formState,...e}}}))}function L(e){return e==="manage"?u.manage.formState:u.booking}function m(e,t){if(e==="manage"){S(t);return}E(t)}function P(){const e=oe(p),t=se(u.view),o=u.view==="book"?u.booking.success?A(u.booking.success,p):j(u.booking,p):re(u.manage,p);b.innerHTML=`
      <div class="px-4 py-10 sm:px-6 lg:px-0">
        <div class="mx-auto w-full max-w-4xl space-y-6">
          ${e}
          ${t}
          ${o}
        </div>
      </div>
    `,H()}function H(){var e,t,o;if(V(),u.view==="book"){if(u.booking.success){(e=b.querySelector("[data-start-over]"))==null||e.addEventListener("click",X);return}q("booking");return}if(u.manage.stage==="lookup"){W();return}if(u.manage.stage==="reschedule"){q("manage"),(t=b.querySelector("[data-manage-reset]"))==null||t.addEventListener("click",D);return}u.manage.stage==="success"&&((o=b.querySelector("[data-manage-start-over]"))==null||o.addEventListener("click",D))}function V(){b.querySelectorAll("[data-view-toggle]").forEach(e=>{e.addEventListener("click",()=>{const t=e.getAttribute("data-view-toggle");!t||t===u.view||$(o=>({...o,view:t}))})})}function q(e){const t=e==="booking"?"#public-booking-form":"#public-reschedule-form",o=b.querySelector(t);if(!o)return;const r=o.querySelector("[data-provider-select]"),s=o.querySelector("[data-service-select]"),n=o.querySelector("[data-date-input]");r==null||r.addEventListener("change",l=>J(l.target.value,e)),s==null||s.addEventListener("change",l=>Y(l.target.value,e)),n==null||n.addEventListener("change",l=>K(l.target.value,e)),o.addEventListener("input",l=>N(l,e)),o.addEventListener("change",l=>N(l,e)),o.addEventListener("submit",l=>ee(l,e)),o.querySelectorAll("[data-slot-option]").forEach(l=>{l.addEventListener("click",()=>{const d=l.getAttribute("data-slot-option");z(d,e)})})}function W(){const e=b.querySelector("#booking-lookup-form");e&&(e.addEventListener("input",G),e.addEventListener("submit",Q))}function X(){E(()=>B(p,h)),y("booking")}function D(){v(()=>U(p,h))}function J(e,t="booking"){m(t,o=>({...o,providerId:e,selectedSlot:null,slotsError:"",errors:{...o.errors,provider_id:void 0,slot_start:void 0}})),y(t)}function Y(e,t="booking"){m(t,o=>({...o,serviceId:e,selectedSlot:null,slotsError:"",errors:{...o.errors,service_id:void 0,slot_start:void 0}})),y(t)}function K(e,t="booking"){m(t,o=>({...o,appointmentDate:e,selectedSlot:null,slotsError:"",errors:{...o.errors,slot_start:void 0}})),y(t)}function z(e,t="booking"){if(!e)return;const o=L(t),r=o.slots.find(s=>s.start===e);!r||o.submitting||m(t,s=>({...s,selectedSlot:r,errors:{...s.errors,slot_start:void 0}}))}function G(e){const{name:t}=e.target;t&&v(o=>({...o,lookupForm:{...o.lookupForm,[t]:e.target.value},lookupErrors:{...o.lookupErrors,[t]:void 0,contact:void 0},lookupError:""}))}async function Q(e){if(e.preventDefault(),u.manage.lookupLoading)return;const t=u.manage.lookupForm,o=(t.token??"").trim(),r=(t.email??"").trim(),s=(t.phone??"").trim(),n={};if(o||(n.token="Enter your confirmation token"),!r&&!s&&(n.contact="Provide the email or phone used on the booking."),Object.keys(n).length>0){v(l=>({...l,lookupErrors:{...l.lookupErrors,...n}}));return}v(l=>({...l,lookupLoading:!0,lookupError:"",lookupErrors:{}}));try{const l=new URLSearchParams;r&&l.set("email",r),s&&l.set("phone",s);const d=l.toString(),f=d?`/public/booking/${encodeURIComponent(o)}?${d}`:`/public/booking/${encodeURIComponent(o)}`,a=await fetch(f,{headers:{Accept:"application/json","X-Requested-With":"XMLHttpRequest"}});I(a.headers);const c=await _(a);if(!a.ok){const x=(c==null?void 0:c.details)??{};throw new w((c==null?void 0:c.error)??"Unable to locate that booking.",x)}v(x=>({...x,lookupLoading:!1})),Z(c==null?void 0:c.data,{email:r,phone:s})}catch(l){if(l instanceof w){v(d=>({...d,lookupLoading:!1,lookupError:l.message,lookupErrors:{...d.lookupErrors,...l.details}}));return}v(d=>({...d,lookupLoading:!1,lookupError:l.message??"Unable to locate that booking."}))}}function Z(e,t={}){if(!e){v(n=>({...n,lookupError:"We could not load that booking. Please try again."}));return}const o=e.start?new Date(e.start):null,r=o&&!Number.isNaN(o.getTime())?o.toISOString().slice(0,10):h,s=e.start?C({start:e.start,end:e.end}):"";v(n=>{var l,d;return{...n,stage:"reschedule",appointment:e,contact:{email:t.email??((l=n.contact)==null?void 0:l.email)??"",phone:t.phone??((d=n.contact)==null?void 0:d.phone)??""},lookupError:"",lookupErrors:{},success:null}}),S(n=>({...n,providerId:String(e.provider_id??""),serviceId:String(e.service_id??""),appointmentDate:r,selectedSlot:e.start?{start:e.start,end:e.end,label:s}:null,form:{...n.form,notes:e.notes??n.form.notes??"",email:n.form.email||t.email||"",phone:n.form.phone||t.phone||""},slots:[],slotsError:"",errors:{},globalError:"",submitting:!1})),u.view!=="manage"&&$(n=>({...n,view:"manage"})),y("manage")}function N(e,t="booking"){const{name:o,type:r}=e.target;if(!o||["provider_id","service_id","appointment_date"].includes(o))return;const s=r==="checkbox"?e.target.checked?"1":"0":e.target.value;m(t,n=>({...n,form:{...n.form,[o]:s},errors:{...n.errors,[o]:void 0}}))}async function ee(e,t="booking"){var r;e.preventDefault();const o=L(t);if(!o.submitting){if(!o.providerId||!o.serviceId){m(t,s=>({...s,errors:{...s.errors,provider_id:o.providerId?void 0:"Select a provider",service_id:o.serviceId?void 0:"Select a service"}}));return}if(!o.selectedSlot){m(t,s=>({...s,errors:{...s.errors,slot_start:"Choose an available time before continuing."}}));return}m(t,s=>({...s,submitting:!0,globalError:"",errors:{...s.errors}}));try{const s=t==="manage"?u.manage.contact??{}:{},n=te(o,s);let l="/public/booking",d="POST";if(t==="manage"){const c=(r=u.manage.appointment)==null?void 0:r.token;if(!c)throw new Error("Missing appointment token.");l=`/public/booking/${encodeURIComponent(c)}`,d="PATCH"}const f=await fetch(l,{method:d,headers:{"Content-Type":"application/json",Accept:"application/json","X-Requested-With":"XMLHttpRequest",...u.csrf.value?{[u.csrf.header]:u.csrf.value}:{}},body:JSON.stringify(n)});I(f.headers);const a=await _(f);if(!f.ok){const c=(a==null?void 0:a.details)??{};throw new w((a==null?void 0:a.error)??(t==="booking"?"Unable to save your booking.":"Unable to update the booking."),c)}t==="booking"?E(c=>({...c,submitting:!1,success:(a==null?void 0:a.data)??null,globalError:""})):(S(c=>({...c,submitting:!1,globalError:""})),v(c=>{var x,k;return{...c,stage:"success",success:(a==null?void 0:a.data)??null,appointment:(a==null?void 0:a.data)??c.appointment,contact:{email:o.form.email??((x=c.contact)==null?void 0:x.email)??"",phone:o.form.phone??((k=c.contact)==null?void 0:k.phone)??""}}}))}catch(s){if(s instanceof w){m(t,n=>({...n,submitting:!1,globalError:s.message,errors:{...n.errors,...s.details}}));return}m(t,n=>({...n,submitting:!1,globalError:s.message??"Something went wrong. Please try again."}))}}}async function y(e="booking"){var r;const t=L(e);if(!t.providerId||!t.serviceId||!t.appointmentDate){m(e,s=>({...s,slots:[],slotsError:""}));return}m(e,s=>({...s,slotsLoading:!0,slotsError:"",slots:[]}));const o=new URLSearchParams({provider_id:t.providerId,service_id:t.serviceId,date:t.appointmentDate});try{const s=await fetch(`/public/booking/slots?${o.toString()}`,{headers:{Accept:"application/json","X-Requested-With":"XMLHttpRequest"}});I(s.headers);const n=await _(s);if(!s.ok)throw new Error((n==null?void 0:n.error)??"Unable to load availability.");const l=Array.isArray(n==null?void 0:n.data)?n.data:[],d=(r=t.selectedSlot)==null?void 0:r.start,f=l.find(a=>a.start===d)??null;m(e,a=>({...a,slotsLoading:!1,slots:l,selectedSlot:f,slotsError:l.length===0?"No slots available for this date. Try another day.":""}))}catch(s){m(e,n=>({...n,slotsLoading:!1,slotsError:s.message??"Unable to load availability."}))}}function te(e,t={}){var r;const o={provider_id:Number(e.providerId),service_id:Number(e.serviceId),slot_start:((r=e.selectedSlot)==null?void 0:r.start)??null,notes:e.form.notes??""};return Object.entries(e.form).forEach(([s,n])=>{o[s]=n}),{...o,...t}}function I(e){if(!e||!u.csrf.header)return;const t=u.csrf.header,o=e.get(t)||e.get(t.toLowerCase());o&&o!==u.csrf.value&&(u.csrf={...u.csrf,value:o},b.dataset.csrfValue=o)}function oe(e){const t=e.timezone??"local timezone";return`
      <header class="text-center">
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Secure Self-Service Booking</p>
        <h1 class="mt-2 text-3xl font-semibold text-slate-900">Reserve an appointment</h1>
        <p class="mt-3 text-base text-slate-600">Pick a provider, choose a service, and lock in a time that works for you. All times are shown in <span class="font-semibold">${i(t)}</span>.</p>
      </header>
    `}function se(e){return`
      <div class="rounded-3xl border border-slate-200 bg-white p-1 shadow-sm">
        <nav class="grid gap-1 sm:flex" role="tablist">
          ${[{key:"book",label:"Book a visit",description:"Plan a new appointment"},{key:"manage",label:"Manage booking",description:"Look up or reschedule"}].map(o=>{const r=o.key===e,s="w-full rounded-2xl px-5 py-3 text-left text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200",n=r?"bg-slate-900 text-white shadow":"text-slate-500 hover:text-slate-900",l=r?"text-slate-200":"text-slate-400";return`
              <button type="button" data-view-toggle="${o.key}" class="${s} ${n}" role="tab" aria-selected="${r}">
                <span class="block">${i(o.label)}</span>
                <span class="text-xs font-normal ${l}">${i(o.description)}</span>
              </button>
            `}).join("")}
        </nav>
      </div>
    `}function re(e,t){return e.stage==="success"&&e.success?A(e.success,t,{title:"Appointment updated",subtitle:"We emailed your updated confirmation. Use the new token for any future changes.",primaryButton:{label:"Look up another booking",attr:"data-manage-start-over"},footerText:"Need to adjust again? Submit your new confirmation token to reopen this booking."}):e.stage==="reschedule"?le(e,t):ne(e,t)}function ne(e,t){var d;const o=e.lookupError?`<div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">${i(e.lookupError)}</div>`:"",r=(d=e.lookupErrors)!=null&&d.contact?`<p class="text-sm text-red-600">${i(e.lookupErrors.contact)}</p>`:"",s=t.reschedulePolicy??{enabled:!0,label:"24 hours"},n=s.enabled?`You can reschedule online up to ${i(s.label??"24 hours")} before the appointment.`:"Online changes are disabled. Contact the office for assistance.",l=s.enabled?"text-slate-600":"text-amber-600";return`
      <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <form id="booking-lookup-form" class="space-y-5" novalidate>
          <div>
            <h2 class="text-xl font-semibold text-slate-900">Already booked?</h2>
            <p class="mt-1 text-sm text-slate-600">Enter your confirmation token plus the email or phone used when booking. We will pull up your appointment instantly.</p>
          </div>
          ${o}
          <label class="block text-sm font-medium text-slate-700">
            Confirmation token
            <input name="token" value="${i(e.lookupForm.token??"")}" class="mt-1 w-full rounded-2xl border-slate-200 px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="abcd-1234-efgh" required>
            ${g("token",e.lookupErrors)}
          </label>
          <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-sm font-medium text-slate-700">
              Email address
              <input type="email" name="email" value="${i(e.lookupForm.email??"")}" class="mt-1 w-full rounded-2xl border-slate-200 px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="you@example.com">
              ${g("email",e.lookupErrors)}
            </label>
            <label class="block text-sm font-medium text-slate-700">
              Phone number
              <input type="tel" name="phone" value="${i(e.lookupForm.phone??"")}" class="mt-1 w-full rounded-2xl border-slate-200 px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="(555) 555-1234">
              ${g("phone",e.lookupErrors)}
            </label>
          </div>
          <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-3 text-sm text-slate-600">
            Provide the contact method used on the booking so we can verify ownership. Email or phone is sufficient.
            ${r}
          </div>
          <p class="text-xs font-medium ${l}">${n}</p>
          <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl border border-transparent bg-blue-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200 disabled:cursor-not-allowed disabled:bg-blue-300" ${e.lookupLoading?"disabled":""}>${e.lookupLoading?"Finding your booking...":"Find my booking"}</button>
        </form>
      </section>
    `}function le(e,t){const r=j(e.formState,t,{formId:"public-reschedule-form",actionOptions:{submitLabel:"Save new time",pendingLabel:"Updating booking...",helperText:"We will send your updated confirmation immediately after you save."}});return`
      <div class="space-y-6">
        ${ie(e,t)}
        ${r}
      </div>
    `}function ie(e,t){var a,c,x,k;const o=e.appointment;if(!o)return"";const r=O(o,t),s=R(o,t),n=T(o),l=((a=e.contact)==null?void 0:a.email)||((c=o.customer)==null?void 0:c.email),d=((x=e.contact)==null?void 0:x.phone)||((k=o.customer)==null?void 0:k.phone),f=l?`Verified via <span class="font-semibold">${i(l)}</span>`:d?`Verified via <span class="font-semibold">${i(d)}</span>`:"Contact verified";return`
      <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Booking token</p>
            <p class="mt-0.5 font-mono text-base text-slate-900">${i(o.token??"")}</p>
            <p class="mt-2 text-sm text-slate-600">${f}</p>
          </div>
          <button type="button" data-manage-reset class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-blue-400 hover:text-blue-600">Use a different token</button>
        </div>
        <dl class="mt-6 grid gap-4 text-left md:grid-cols-3">
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Current time</dt>
            <dd class="text-base font-semibold text-slate-900">${i(n)}</dd>
          </div>
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Provider</dt>
            <dd class="text-base font-semibold text-slate-900">${i(r)}</dd>
          </div>
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Service</dt>
            <dd class="text-base font-semibold text-slate-900">${i(s)}</dd>
          </div>
        </dl>
      </section>
    `}function j(e,t,o={}){const r=e.globalError?`<div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">${i(e.globalError)}</div>`:"";return`
      <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <form id="${o.formId??"public-booking-form"}" class="space-y-6" novalidate>
          ${r}
          ${ae(e,t)}
          ${de(e)}
          ${ce(e,t)}
          ${ue(e,t)}
          ${fe(e,t)}
          ${be(t)}
          ${me(e,o.actionOptions)}
        </form>
      </section>
    `}function ae(e,t){var l,d;const o=(t.providers??[]).map(f=>{const a=i(String(f.id??"")),c=String(f.id)===String(e.providerId)?"selected":"";return`
        <option value="${a}" ${c}>
          ${i(f.name??f.displayName??"Provider")}
        </option>
      `}).join(""),r=(t.services??[]).map(f=>{const a=i(String(f.id??"")),c=String(f.id)===String(e.serviceId)?"selected":"";return`
        <option value="${a}" ${c}>
          ${i(f.name??"Service")}${f.formattedPrice?` &middot; ${i(f.formattedPrice)}`:""}
        </option>
      `}).join(""),s=(t.services??[]).find(f=>String(f.id)===String(e.serviceId)),n=s?`<p class="text-sm text-slate-500">${i(s.name??"Service")} &middot; ${(s.duration??s.durationMinutes??0)||0} min${s.formattedPrice?` &middot; ${i(s.formattedPrice)}`:""}</p>`:"";return`
      <div class="grid gap-4 md:grid-cols-2">
        <label class="block text-sm font-medium text-slate-700">
          Provider
          <select name="provider_id" data-provider-select class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" ${(l=t.providers)!=null&&l.length?"":"disabled"}>
            <option value="" ${e.providerId?"":"selected"}>Choose a provider</option>
            ${o}
          </select>
          ${g("provider_id",e.errors)}
        </label>
        <label class="block text-sm font-medium text-slate-700">
          Service
          <select name="service_id" data-service-select class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" ${(d=t.services)!=null&&d.length?"":"disabled"}>
            <option value="" ${e.serviceId?"":"selected"}>Choose a service</option>
            ${r}
          </select>
          ${n}
          ${g("service_id",e.errors)}
        </label>
      </div>
      <div class="grid gap-4 md:grid-cols-2">
        <label class="block text-sm font-medium text-slate-700">
          Preferred date
          <input type="date" data-date-input name="appointment_date" min="${new Date().toISOString().slice(0,10)}" value="${i(e.appointmentDate)}" class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
        </label>
        <div class="flex flex-col rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
          <span class="font-semibold text-slate-700">Scheduling tips</span>
          <span>Selecting a different provider or day can reveal more openings. Slots refresh in real-time.</span>
        </div>
      </div>
    `}function de(e){const t=e.slotsLoading?'<p class="text-sm text-slate-500">Checking availability...</p>':"",o=e.slots.map(n=>{var a;const l=n.start===((a=e.selectedSlot)==null?void 0:a.start),d="w-full rounded-2xl border px-3 py-2 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200",f=l?"border-blue-600 bg-blue-50 text-blue-900 shadow-sm":"border-slate-200 text-slate-700 hover:border-blue-400 hover:text-blue-700";return`<button type="button" data-slot-option="${n.start}" class="${d} ${f}">${i(n.label??xe(n))}</button>`}).join(""),r=o?`<div class="grid gap-2 sm:grid-cols-2">${o}</div>`:"",s=!e.slotsLoading&&!e.slots.length?`<p class="text-sm text-slate-500">${e.providerId&&e.serviceId?i(e.slotsError||"No open times for this day. Try another date."):"Select a provider and service to view appointments."}</p>`:"";return`
      <div>
        <div class="flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-900">Pick an available time</h2>
          ${e.selectedSlot?`<span class="text-sm text-slate-600">Selected: ${i(C(e.selectedSlot))}</span>`:""}
        </div>
        <div class="mt-3 space-y-3">
          ${t}
          ${r}
          ${s}
          ${g("slot_start",e.errors)}
        </div>
      </div>
    `}function ce(e,t){const o=t.fieldConfig??{},s=["first_name","last_name","email","phone","address"].filter(l=>{var d;return((d=o[l])==null?void 0:d.display)!==!1});return s.length?`
      <div>
        <h2 class="text-base font-semibold text-slate-900">Your details</h2>
        <p class="text-sm text-slate-500">We will use this information to confirm your appointment and send reminders.</p>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
          ${s.map(l=>pe(l,o[l]??{},e)).join("")}
        </div>
      </div>
    `:""}function ue(e,t){const o=t.customFieldConfig??{},r=Object.keys(o);if(!r.length)return"";const s=r.map(n=>ge(n,o[n],e)).filter(Boolean).join("");return s?`
      <div>
        <h2 class="text-base font-semibold text-slate-900">Additional information</h2>
        <div class="mt-4 grid gap-4">
          ${s}
        </div>
      </div>
    `:""}function fe(e,t){var s,n;const o=t.fieldConfig??{};return((s=o.notes)==null?void 0:s.display)===!1?"":`
      <div>
        <label class="block text-sm font-medium text-slate-700">
          Notes for your provider ${((n=o.notes)==null?void 0:n.required)?'<span class="text-red-500">*</span>':""}
          <textarea name="notes" rows="4" class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">${i(e.form.notes??"")}</textarea>
        </label>
        ${g("notes",e.errors)}
      </div>
    `}function be(e){const t=e.reschedulePolicy??{enabled:!0,label:"24 hours"};return`<p class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">${t.enabled?`Need to make a change? You can reschedule online up to ${i(t.label??"24 hours")} before your appointment.`:"Contact the office directly if you need to make a change."}</p>`}function me(e,t={}){const o=(t==null?void 0:t.submitLabel)??"Confirm appointment",r=(t==null?void 0:t.pendingLabel)??"Booking your appointment...",s=(t==null?void 0:t.helperText)??"We respect your privacy. Your confirmation token will be displayed and emailed immediately.",n=e.submitting?"disabled":"",l=e.submitting?r:o;return`
      <div class="flex flex-col gap-3">
        <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-transparent bg-blue-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200 disabled:cursor-not-allowed disabled:bg-blue-300" ${n}>${i(l)}</button>
        <p class="text-center text-xs text-slate-400">${i(s)}</p>
      </div>
    `}function A(e,t,o={}){if(!e)return"";const r=O(e,t),s=R(e,t),n=T(e),l=o.title??"You're booked!",d=o.subtitle??"We'll send a confirmation email shortly. Keep your token handy if you need to make changes.",f=o.footerText??"Need to reschedule? Use your token and contact email to pull up this booking anytime.",a=o.primaryButton??{label:"Book another appointment",attr:"data-start-over"},c=o.secondaryButton,x=(a==null?void 0:a.attr)??"data-start-over",k=(c==null?void 0:c.attr)??"";return`
      <section class="rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-sm">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
          <span class="text-2xl">&#10003;</span>
        </div>
        <h2 class="mt-4 text-2xl font-semibold text-slate-900">${i(l)}</h2>
        <p class="mt-2 text-sm text-slate-600">${i(d)}</p>
        <dl class="mt-6 grid gap-4 text-left md:grid-cols-2">
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Date & time</dt>
            <dd class="text-base font-semibold text-slate-900">${i(n)}</dd>
          </div>
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Provider</dt>
            <dd class="text-base font-semibold text-slate-900">${i(r)}</dd>
          </div>
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Service</dt>
            <dd class="text-base font-semibold text-slate-900">${i(s)}</dd>
          </div>
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Confirmation token</dt>
            <dd class="text-base font-mono text-slate-900">${i(e.token??"")}</dd>
          </div>
        </dl>
        <div class="mt-6 flex flex-col gap-3">
          ${a?`<button type="button" ${x} class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-6 py-3 text-base font-semibold text-slate-700 transition hover:border-blue-500 hover:text-blue-600">${i(a.label)}</button>`:""}
          ${c?`<button type="button" ${k} class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-6 py-3 text-base font-semibold text-slate-700 transition hover:border-blue-400 hover:text-blue-600">${i(c.label)}</button>`:""}
          <p class="text-xs text-slate-500">${i(f)}</p>
        </div>
      </section>
    `}function pe(e,t,o){const r=t.label??$e[e]??e,s=t.required;return`
      <label class="block text-sm font-medium text-slate-700">
        ${i(r)} ${s?'<span class="text-red-500">*</span>':""}
        <input name="${e}" value="${i(o.form[e]??"")}" type="${e==="email"?"email":e==="phone"?"tel":"text"}" class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" ${s?'aria-required="true"':""}>
        ${g(e,o.errors)}
      </label>
    `}function ge(e,t,o){if(!t||t.display===!1)return"";const r=t.title??`Custom field ${t.index}`;if(t.type==="textarea")return`
        <label class="block text-sm font-medium text-slate-700">
          ${i(r)} ${t.required?'<span class="text-red-500">*</span>':""}
          <textarea name="${e}" rows="3" class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">${i(o.form[e]??"")}</textarea>
          ${g(e,o.errors)}
        </label>
      `;if(t.type==="checkbox"){const s=(o.form[e]??"")==="1"?"checked":"";return`
        <label class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700">
          <span>${i(r)}</span>
          <input type="checkbox" name="${e}" class="h-4 w-4" ${s}>
        </label>
        ${g(e,o.errors)}
      `}return`
      <label class="block text-sm font-medium text-slate-700">
        ${i(r)} ${t.required?'<span class="text-red-500">*</span>':""}
        <input name="${e}" value="${i(o.form[e]??"")}" type="text" class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
        ${g(e,o.errors)}
      </label>
    `}function g(e,t){return!t||!t[e]?"":`<p class="mt-1 text-sm text-red-600">${i(t[e])}</p>`}function xe(e){const t=e!=null&&e.start?new Date(e.start):null,o=e!=null&&e.end?new Date(e.end):null;if(!t||!o)return"Selected time";const r=new Intl.DateTimeFormat(void 0,{hour:"numeric",minute:"2-digit"});return`${r.format(t)} - ${r.format(o)}`}function C(e){if(!(e!=null&&e.start))return"";const t=new Date(e.start),o=e.end?new Date(e.end):null,r=new Intl.DateTimeFormat(void 0,{weekday:"short",month:"short",day:"numeric"}),s=new Intl.DateTimeFormat(void 0,{hour:"numeric",minute:"2-digit"}),n=r.format(t),l=o?`${s.format(t)} - ${s.format(o)}`:s.format(t);return`${n}, ${l}`}function T(e){return e?e.display_range?e.display_range:C({start:e.start,end:e.end}):""}function O(e,t){var r;if((r=e==null?void 0:e.provider)!=null&&r.name)return e.provider.name;const o=(t.providers??[]).find(s=>Number(s.id)===Number(e==null?void 0:e.provider_id));return(o==null?void 0:o.name)??(o==null?void 0:o.displayName)??"Assigned provider"}function R(e,t){var r;if((r=e==null?void 0:e.service)!=null&&r.name)return e.service.name;const o=(t.services??[]).find(s=>Number(s.id)===Number(e==null?void 0:e.service_id));return(o==null?void 0:o.name)??"Selected service"}function ve(){try{return JSON.parse(b.dataset.context??"{}")||window.__PUBLIC_BOOKING__||{}}catch(e){return console.error("[public-booking] Failed to parse context payload.",e),window.__PUBLIC_BOOKING__||{}}}function M(e){const t={first_name:"",last_name:"",email:"",phone:"",address:"",notes:""},o=e.fieldConfig??{};Object.keys(o).forEach(s=>{t[s]===void 0&&(t[s]="")});const r=e.customFieldConfig??{};return Object.keys(r).forEach(s=>{t[s]=r[s].type==="checkbox"?"0":""}),t}function B(e,t){var o,r,s,n,l,d;return{providerId:((s=(r=(o=e.providers)==null?void 0:o[0])==null?void 0:r.id)==null?void 0:s.toString())??"",serviceId:((d=(l=(n=e.services)==null?void 0:n[0])==null?void 0:l.id)==null?void 0:d.toString())??"",appointmentDate:t,slots:[],slotsLoading:!1,slotsError:"",selectedSlot:null,form:M(e),errors:{},globalError:"",submitting:!1,success:null}}function U(e,t){return{stage:"lookup",lookupForm:{token:"",email:"",phone:""},lookupErrors:{},lookupError:"",lookupLoading:!1,appointment:null,success:null,contact:{email:"",phone:""},formState:{providerId:"",serviceId:"",appointmentDate:t,slots:[],slotsLoading:!1,slotsError:"",selectedSlot:null,form:M(e),errors:{},globalError:"",submitting:!1}}}async function _(e){try{return await e.json()}catch{return null}}function he(e){return typeof structuredClone=="function"?structuredClone(e):JSON.parse(JSON.stringify(e))}function ke(){const e=document.activeElement;return!e||!b.contains(e)?null:e instanceof HTMLInputElement||e instanceof HTMLTextAreaElement?{name:e.name||null,selectionStart:e.selectionStart,selectionEnd:e.selectionEnd}:null}function ye(e){if(!(e!=null&&e.name))return;const t=`input[name="${e.name}"]`,o=b.querySelector(t)||b.querySelector(`textarea[name="${e.name}"]`);if(o instanceof HTMLInputElement||o instanceof HTMLTextAreaElement){o.focus({preventScroll:!0});const r=typeof e.selectionStart=="number"&&typeof e.selectionEnd=="number";try{if(r)o.setSelectionRange(e.selectionStart,e.selectionEnd);else{const s=o.value.length;o.setSelectionRange(s,s)}}catch{}}}}class w extends Error{constructor(F,h={}){super(F),this.details=h}}function i(p){return p==null?"":String(p).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;")}
