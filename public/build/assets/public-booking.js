const p=document.getElementById("public-booking-root"),Xe={first_name:"First name",last_name:"Last name",email:"Email address",phone:"Phone number",address:"Address",notes:"Notes"};p?ze():console.warn("[public-booking] Root element not found.");function ze(){var Z;const g=Me(),P=new Date,$=g.initialAvailability??null,x=g.initialCalendar??null,T=Array.isArray(x==null?void 0:x.availableDates)?x.availableDates:[],ne=(x==null?void 0:x.defaultDate)??T[0]??null,w=($==null?void 0:$.date)??ne??P.toISOString().slice(0,10),le=((Z=x==null?void 0:x.slotsByDate)==null?void 0:Z[w])??[],ae=T.length>0&&le.length>0;let f={view:"book",booking:G(g,w,$,x),manage:Q(g,w),csrf:{header:p.dataset.csrfHeader||"X-CSRF-TOKEN",value:p.dataset.csrfValue||"",name:p.dataset.csrfName||"csrf_token"}};B(),f.booking.providerId&&f.booking.serviceId&&(ae?U("booking"):_("booking"));function L(e){const t=We();f=typeof e=="function"?e(He(f)):{...f,...e},B(),Ve(t)}function q(e){L(t=>({...t,booking:typeof e=="function"?e(t.booking):{...t.booking,...e}}))}function y(e){L(t=>({...t,manage:typeof e=="function"?e(t.manage):{...t.manage,...e}}))}function N(e){L(t=>({...t,manage:{...t.manage,formState:typeof e=="function"?e(t.manage.formState):{...t.manage.formState,...e}}}))}function E(e){return e==="manage"?f.manage.formState:f.booking}function m(e,t){if(e==="manage"){N(t);return}q(t)}function B(){const e=$e(g),t=we(f.view),o=f.view==="book"?f.booking.success?W(f.booking.success,g):H(f.booking,g):Ee(f.manage,g);p.innerHTML=`
      <div class="px-4 py-10 sm:px-6 lg:px-0">
        <div class="mx-auto w-full max-w-4xl space-y-6">
          ${e}
          ${t}
          ${o}
        </div>
      </div>
    `,ie()}function ie(){var e,t,o;if(de(),f.view==="book"){if(f.booking.success){(e=p.querySelector("[data-start-over]"))==null||e.addEventListener("click",ue);return}R("booking");return}if(f.manage.stage==="lookup"){ce();return}if(f.manage.stage==="reschedule"){R("manage"),(t=p.querySelector("[data-manage-reset]"))==null||t.addEventListener("click",M);return}f.manage.stage==="success"&&((o=p.querySelector("[data-manage-start-over]"))==null||o.addEventListener("click",M))}function de(){p.querySelectorAll("[data-view-toggle]").forEach(e=>{e.addEventListener("click",()=>{const t=e.getAttribute("data-view-toggle");!t||t===f.view||L(o=>({...o,view:t}))})})}function R(e){const t=e==="booking"?"#public-booking-form":"#public-reschedule-form",o=p.querySelector(t);if(!o)return;const s=o.querySelector("[data-provider-select]"),r=o.querySelector("[data-service-select]"),n=o.querySelector("[data-date-input]"),l=o.querySelector("[data-date-select]");s==null||s.addEventListener("change",i=>fe(i.target.value,e)),r==null||r.addEventListener("change",i=>be(i.target.value,e)),n==null||n.addEventListener("change",i=>A(i.target.value,e)),l==null||l.addEventListener("change",i=>A(i.target.value,e)),o.querySelectorAll("[data-date-pill]").forEach(i=>{i.addEventListener("click",()=>{const u=i.getAttribute("data-date-pill");A(u,e)})}),o.addEventListener("input",i=>O(i,e)),o.addEventListener("change",i=>O(i,e)),o.addEventListener("submit",i=>ve(i,e)),o.querySelectorAll("[data-slot-option]").forEach(i=>{i.addEventListener("click",()=>{const u=i.getAttribute("data-slot-option");pe(u,e)})})}function ce(){const e=p.querySelector("#booking-lookup-form");e&&(e.addEventListener("input",ge),e.addEventListener("submit",xe))}function ue(){q(()=>G(g,w,$,x)),_("booking")}function M(){y(()=>Q(g,w))}function fe(e,t="booking"){m(t,o=>({...o,providerId:e,serviceId:"",services:[],servicesLoading:!0,selectedSlot:null,slots:[],slotsError:"",prefetched:null,calendar:k(),errors:{...o.errors,provider_id:void 0,service_id:void 0,slot_start:void 0}})),e&&me(e,t)}function be(e,t="booking"){m(t,o=>({...o,serviceId:e,selectedSlot:null,slots:[],slotsError:"",prefetched:null,calendar:{...k(),loading:!0,error:""},errors:{...o.errors,service_id:void 0,slot_start:void 0}})),e&&_(t)}async function me(e,t="booking"){if(!e){m(t,o=>({...o,services:[],servicesLoading:!1}));return}try{const o=await fetch(`/api/v1/providers/${e}/services`,{headers:{Accept:"application/json","X-Requested-With":"XMLHttpRequest"}});S(o.headers);const s=await I(o);if(!o.ok)throw new Error((s==null?void 0:s.error)??"Unable to load services.");const r=((s==null?void 0:s.data)??s??[]).map(n=>({id:n.id,name:n.name,duration:n.duration_min??n.durationMin??n.duration,durationMinutes:n.duration_min??n.durationMin??n.duration,price:n.price,formattedPrice:n.price?`$${parseFloat(n.price).toFixed(2)}`:""}));m(t,n=>({...n,services:r,servicesLoading:!1}))}catch(o){console.error("[public-booking] Failed to fetch services:",o),m(t,s=>({...s,services:[],servicesLoading:!1}))}}function A(e,t="booking"){var r;if(!e)return;if(m(t,n=>({...n,errors:{...n.errors,slot_start:void 0}})),(((r=E(t).calendar)==null?void 0:r.availableDates)??[]).includes(e)){U(t,e);return}m(t,n=>({...n,appointmentDate:e,selectedSlot:null,slots:[],slotsError:"",prefetched:null})),ye(t,{forceRemote:!0})}function pe(e,t="booking"){if(!e)return;const o=E(t),s=o.slots.find(r=>r.start===e);!s||o.submitting||m(t,r=>({...r,selectedSlot:s,errors:{...r.errors,slot_start:void 0}}))}function ge(e){const{name:t}=e.target;t&&y(o=>({...o,lookupForm:{...o.lookupForm,[t]:e.target.value},lookupErrors:{...o.lookupErrors,[t]:void 0,contact:void 0},lookupError:""}))}async function xe(e){if(e.preventDefault(),f.manage.lookupLoading)return;const t=f.manage.lookupForm,o=(t.token??"").trim(),s=(t.email??"").trim(),r=(t.phone??"").trim(),n={};if(o||(n.token="Enter your confirmation token"),!s&&!r&&(n.contact="Provide the email or phone used on the booking."),Object.keys(n).length>0){y(l=>({...l,lookupErrors:{...l.lookupErrors,...n}}));return}y(l=>({...l,lookupLoading:!0,lookupError:"",lookupErrors:{}}));try{const l=new URLSearchParams;s&&l.set("email",s),r&&l.set("phone",r);const i=l.toString(),u=i?`/public/booking/${encodeURIComponent(o)}?${i}`:`/public/booking/${encodeURIComponent(o)}`,a=await fetch(u,{headers:{Accept:"application/json","X-Requested-With":"XMLHttpRequest"}});S(a.headers);const d=await I(a);if(!a.ok){const b=(d==null?void 0:d.details)??{};throw new F((d==null?void 0:d.error)??"Unable to locate that booking.",b)}y(b=>({...b,lookupLoading:!1})),he(d==null?void 0:d.data,{email:s,phone:r})}catch(l){if(l instanceof F){y(i=>({...i,lookupLoading:!1,lookupError:l.message,lookupErrors:{...i.lookupErrors,...l.details}}));return}y(i=>({...i,lookupLoading:!1,lookupError:l.message??"Unable to locate that booking."}))}}function he(e,t={}){if(!e){y(n=>({...n,lookupError:"We could not load that booking. Please try again."}));return}const o=e.start?new Date(e.start):null,s=o&&!Number.isNaN(o.getTime())?o.toISOString().slice(0,10):w,r=e.start?j({start:e.start,end:e.end}):"";y(n=>{var l,i;return{...n,stage:"reschedule",appointment:e,contact:{email:t.email??((l=n.contact)==null?void 0:l.email)??"",phone:t.phone??((i=n.contact)==null?void 0:i.phone)??""},lookupError:"",lookupErrors:{},success:null}}),N(n=>({...n,providerId:String(e.provider_id??""),serviceId:String(e.service_id??""),appointmentDate:s,selectedSlot:e.start?{start:e.start,end:e.end,label:r}:null,form:{...n.form,notes:e.notes??n.form.notes??"",email:n.form.email||t.email||"",phone:n.form.phone||t.phone||""},slots:[],slotsError:"",errors:{},globalError:"",submitting:!1,calendar:k()})),f.view!=="manage"&&L(n=>({...n,view:"manage"})),_("manage",{preferredDate:s})}function O(e,t="booking"){const{name:o,type:s}=e.target;if(!o||["provider_id","service_id","appointment_date"].includes(o))return;const r=s==="checkbox"?e.target.checked?"1":"0":e.target.value;m(t,n=>({...n,form:{...n.form,[o]:r},errors:{...n.errors,[o]:void 0}}))}async function ve(e,t="booking"){var s;e.preventDefault();const o=E(t);if(!o.submitting){if(!o.providerId||!o.serviceId){m(t,r=>({...r,errors:{...r.errors,provider_id:o.providerId?void 0:"Select a provider",service_id:o.serviceId?void 0:"Select a service"}}));return}if(!o.selectedSlot){m(t,r=>({...r,errors:{...r.errors,slot_start:"Choose an available time before continuing."}}));return}m(t,r=>({...r,submitting:!0,globalError:"",errors:{...r.errors}}));try{const r=t==="manage"?f.manage.contact??{}:{},n=ke(o,r);let l="/public/booking",i="POST";if(t==="manage"){const d=(s=f.manage.appointment)==null?void 0:s.token;if(!d)throw new Error("Missing appointment token.");l=`/public/booking/${encodeURIComponent(d)}`,i="PATCH"}const u=await fetch(l,{method:i,headers:{"Content-Type":"application/json",Accept:"application/json","X-Requested-With":"XMLHttpRequest",...f.csrf.value?{[f.csrf.header]:f.csrf.value}:{}},body:JSON.stringify(n)});S(u.headers);const a=await I(u);if(!u.ok){const d=(a==null?void 0:a.details)??{};throw new F((a==null?void 0:a.error)??(t==="booking"?"Unable to save your booking.":"Unable to update the booking."),d)}t==="booking"?q(d=>({...d,submitting:!1,success:(a==null?void 0:a.data)??null,globalError:""})):(N(d=>({...d,submitting:!1,globalError:""})),y(d=>{var b,h;return{...d,stage:"success",success:(a==null?void 0:a.data)??null,appointment:(a==null?void 0:a.data)??d.appointment,contact:{email:o.form.email??((b=d.contact)==null?void 0:b.email)??"",phone:o.form.phone??((h=d.contact)==null?void 0:h.phone)??""}}}))}catch(r){if(r instanceof F){m(t,n=>({...n,submitting:!1,globalError:r.message,errors:{...n.errors,...r.details}}));return}m(t,n=>({...n,submitting:!1,globalError:r.message??"Something went wrong. Please try again."}))}}}async function _(e="booking",t={}){const{preferredDate:o=null}=t,s=E(e);if(!s.providerId||!s.serviceId){m(e,n=>({...n,calendar:k(),slots:[],slotsError:"",selectedSlot:null,prefetched:null}));return}m(e,n=>({...n,calendar:{...k(),loading:!0,error:""},slots:[],slotsError:"",selectedSlot:null,prefetched:null,slotsLoading:!0}));const r=new URLSearchParams({provider_id:s.providerId,service_id:s.serviceId,days:"60"});try{const n=await fetch(`/public/booking/calendar?${r.toString()}`,{headers:{Accept:"application/json","X-Requested-With":"XMLHttpRequest"}});S(n.headers);const l=await I(n);if(!n.ok)throw new Error((l==null?void 0:l.error)??"Unable to load availability.");const i=(l==null?void 0:l.data)??l??{};m(e,u=>{const a={...k(i),loading:!1,error:""},d=J(u,a,o);return{...u,...d,calendar:a,slotsLoading:!1}})}catch(n){m(e,l=>({...l,calendar:{...l.calendar,loading:!1,error:n.message??"Unable to load availability."},slotsLoading:!1}))}}function U(e="booking",t=null){const o=E(e);!o.calendar||!Array.isArray(o.calendar.availableDates)||!o.calendar.availableDates.length||m(e,s=>{var l,i;const r={...k(s.calendar),loading:((l=s.calendar)==null?void 0:l.loading)??!1,error:((i=s.calendar)==null?void 0:i.error)??""},n=J(s,r,t);return{...s,...n,calendar:r}})}async function ye(e="booking",t={}){var l,i,u;const{forceRemote:o=!1}=t,s=E(e);if(!s.providerId||!s.serviceId||!s.appointmentDate){m(e,a=>({...a,slots:[],slotsError:""}));return}const r=(i=(l=s.calendar)==null?void 0:l.slotsByDate)==null?void 0:i[s.appointmentDate];if(!o&&Array.isArray(r)){m(e,a=>({...a,slots:r,slotsLoading:!1,slotsError:r.length===0?"No slots available for this date. Try another day.":"",selectedSlot:r.find(d=>{var b;return d.start===((b=a.selectedSlot)==null?void 0:b.start)})??null}));return}m(e,a=>({...a,slotsLoading:!0,slotsError:"",slots:[]}));const n=new URLSearchParams({provider_id:s.providerId,service_id:s.serviceId,date:s.appointmentDate});try{const a=await fetch(`/public/booking/slots?${n.toString()}`,{headers:{Accept:"application/json","X-Requested-With":"XMLHttpRequest"}});S(a.headers);const d=await I(a);if(!a.ok)throw new Error((d==null?void 0:d.error)??"Unable to load availability.");const b=Array.isArray(d==null?void 0:d.data)?d.data:[],h=(u=s.selectedSlot)==null?void 0:u.start,C=b.find(D=>D.start===h)??null;m(e,D=>({...D,slotsLoading:!1,slots:b,selectedSlot:C,slotsError:b.length===0?"No slots available for this date. Try another day.":""}))}catch(a){m(e,d=>({...d,slotsLoading:!1,slotsError:a.message??"Unable to load availability."}))}}function ke(e,t={}){var s;const o={provider_id:Number(e.providerId),service_id:Number(e.serviceId),slot_start:((s=e.selectedSlot)==null?void 0:s.start)??null,notes:e.form.notes??""};return Object.entries(e.form).forEach(([r,n])=>{o[r]=n}),{...o,...t}}function S(e){if(!e||!f.csrf.header)return;const t=f.csrf.header,o=e.get(t)||e.get(t.toLowerCase());o&&o!==f.csrf.value&&(f.csrf={...f.csrf,value:o},p.dataset.csrfValue=o)}function $e(e){const t=e.timezone??"local timezone";return`
      <header class="text-center">
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Secure Self-Service Booking</p>
        <h1 class="mt-2 text-3xl font-semibold text-slate-900">Reserve an appointment</h1>
        <p class="mt-3 text-base text-slate-600">Pick a provider, choose a service, and lock in a time that works for you. All times are shown in <span class="font-semibold">${c(t)}</span>.</p>
      </header>
    `}function we(e){return`
      <div class="rounded-3xl border border-slate-200 bg-white p-1 shadow-sm">
        <nav class="grid gap-1 sm:flex" role="tablist">
          ${[{key:"book",label:"Book a visit",description:"Plan a new appointment"},{key:"manage",label:"Manage booking",description:"Look up or reschedule"}].map(o=>{const s=o.key===e,r="w-full rounded-2xl px-5 py-3 text-left text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200",n=s?"bg-slate-900 text-white shadow":"text-slate-500 hover:text-slate-900",l=s?"text-slate-200":"text-slate-400";return`
              <button type="button" data-view-toggle="${o.key}" class="${r} ${n}" role="tab" aria-selected="${s}">
                <span class="block">${c(o.label)}</span>
                <span class="text-xs font-normal ${l}">${c(o.description)}</span>
              </button>
            `}).join("")}
        </nav>
      </div>
    `}function Ee(e,t){return e.stage==="success"&&e.success?W(e.success,t,{title:"Appointment updated",subtitle:"We emailed your updated confirmation. Use the new token for any future changes.",primaryButton:{label:"Look up another booking",attr:"data-manage-start-over"},footerText:"Need to adjust again? Submit your new confirmation token to reopen this booking."}):e.stage==="reschedule"?Le(e,t):De(e,t)}function De(e,t){var i;const o=e.lookupError?`<div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">${c(e.lookupError)}</div>`:"",s=(i=e.lookupErrors)!=null&&i.contact?`<p class="text-sm text-red-600">${c(e.lookupErrors.contact)}</p>`:"",r=t.reschedulePolicy??{enabled:!0,label:"24 hours"},n=r.enabled?`You can reschedule online up to ${c(r.label??"24 hours")} before the appointment.`:"Online changes are disabled. Contact the office for assistance.",l=r.enabled?"text-slate-600":"text-amber-600";return`
      <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <form id="booking-lookup-form" class="space-y-5" novalidate>
          <div>
            <h2 class="text-xl font-semibold text-slate-900">Already booked?</h2>
            <p class="mt-1 text-sm text-slate-600">Enter your confirmation token plus the email or phone used when booking. We will pull up your appointment instantly.</p>
          </div>
          ${o}
          <label class="block text-sm font-medium text-slate-700">
            Confirmation token
            <input name="token" value="${c(e.lookupForm.token??"")}" class="mt-1 w-full rounded-2xl border-slate-200 px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="abcd-1234-efgh" required>
            ${v("token",e.lookupErrors)}
          </label>
          <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-sm font-medium text-slate-700">
              Email address
              <input type="email" name="email" value="${c(e.lookupForm.email??"")}" class="mt-1 w-full rounded-2xl border-slate-200 px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="you@example.com">
              ${v("email",e.lookupErrors)}
            </label>
            <label class="block text-sm font-medium text-slate-700">
              Phone number
              <input type="tel" name="phone" value="${c(e.lookupForm.phone??"")}" class="mt-1 w-full rounded-2xl border-slate-200 px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="(555) 555-1234">
              ${v("phone",e.lookupErrors)}
            </label>
          </div>
          <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-3 text-sm text-slate-600">
            Provide the contact method used on the booking so we can verify ownership. Email or phone is sufficient.
            ${s}
          </div>
          <p class="text-xs font-medium ${l}">${n}</p>
          <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl border border-transparent bg-blue-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200 disabled:cursor-not-allowed disabled:bg-blue-300" ${e.lookupLoading?"disabled":""}>${e.lookupLoading?"Finding your booking...":"Find my booking"}</button>
        </form>
      </section>
    `}function Le(e,t){const s=H(e.formState,t,{formId:"public-reschedule-form",actionOptions:{submitLabel:"Save new time",pendingLabel:"Updating booking...",helperText:"We will send your updated confirmation immediately after you save."}});return`
      <div class="space-y-6">
        ${Se(e,t)}
        ${s}
      </div>
    `}function Se(e,t){var a,d,b,h;const o=e.appointment;if(!o)return"";const s=X(o,t),r=z(o,t),n=V(o),l=((a=e.contact)==null?void 0:a.email)||((d=o.customer)==null?void 0:d.email),i=((b=e.contact)==null?void 0:b.phone)||((h=o.customer)==null?void 0:h.phone),u=l?`Verified via <span class="font-semibold">${c(l)}</span>`:i?`Verified via <span class="font-semibold">${c(i)}</span>`:"Contact verified";return`
      <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Booking token</p>
            <p class="mt-0.5 font-mono text-base text-slate-900">${c(o.token??"")}</p>
            <p class="mt-2 text-sm text-slate-600">${u}</p>
          </div>
          <button type="button" data-manage-reset class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-blue-400 hover:text-blue-600">Use a different token</button>
        </div>
        <dl class="mt-6 grid gap-4 text-left md:grid-cols-3">
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Current time</dt>
            <dd class="text-base font-semibold text-slate-900">${c(n)}</dd>
          </div>
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Provider</dt>
            <dd class="text-base font-semibold text-slate-900">${c(s)}</dd>
          </div>
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Service</dt>
            <dd class="text-base font-semibold text-slate-900">${c(r)}</dd>
          </div>
        </dl>
      </section>
    `}function H(e,t,o={}){const s=e.globalError?`<div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">${c(e.globalError)}</div>`:"";return`
      <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <form id="${o.formId??"public-booking-form"}" class="space-y-6" novalidate>
          ${s}
          ${Ie(e,t)}
          ${_e(e)}
          ${Pe(e,t)}
          ${qe(e,t)}
          ${Ne(e,t)}
          ${Ae(t)}
          ${je(e,o.actionOptions)}
        </form>
      </section>
    `}function Ie(e,t){var i,u;const o=(t.providers??[]).map(a=>{const d=c(String(a.id??"")),b=String(a.id)===String(e.providerId)?"selected":"";return`
        <option value="${d}" ${b}>
          ${c(a.name??a.displayName??"Provider")}
        </option>
      `}).join(""),s=(i=e.services)!=null&&i.length?e.services:t.services??[],r=s.map(a=>{const d=c(String(a.id??"")),b=String(a.id)===String(e.serviceId)?"selected":"";return`
        <option value="${d}" ${b}>
          ${c(a.name??"Service")}${a.formattedPrice?` &middot; ${c(a.formattedPrice)}`:""}
        </option>
      `}).join(""),n=s.find(a=>String(a.id)===String(e.serviceId)),l=n?`<p class="text-sm text-slate-500">${c(n.name??"Service")} &middot; ${(n.duration??n.durationMinutes??0)||0} min${n.formattedPrice?` &middot; ${c(n.formattedPrice)}`:""}</p>`:"";return`
      <div class="grid gap-4 md:grid-cols-2">
        <label class="block text-sm font-medium text-slate-700">
          Provider
          <select name="provider_id" data-provider-select class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" ${(u=t.providers)!=null&&u.length?"":"disabled"}>
            <option value="" ${e.providerId?"":"selected"}>Choose a provider</option>
            ${o}
          </select>
          ${v("provider_id",e.errors)}
        </label>
        <label class="block text-sm font-medium text-slate-700">
          Service
          <select name="service_id" data-service-select class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" ${e.servicesLoading||!e.providerId?"disabled":""}>
            <option value="" ${e.serviceId?"":"selected"}>${e.servicesLoading?"Loading services...":e.providerId?"Choose a service":"Select a provider first"}</option>
            ${r}
          </select>
          ${l}
          ${v("service_id",e.errors)}
        </label>
      </div>
      <div class="grid gap-4 md:grid-cols-2">
        ${Fe(e)}
        ${Ce()}
      </div>
    `}function _e(e){const t=e.slotsLoading?'<p class="text-sm text-slate-500">Checking availability...</p>':"",o=e.slots.map(n=>{var a;const l=n.start===((a=e.selectedSlot)==null?void 0:a.start),i="w-full rounded-2xl border px-3 py-2 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200",u=l?"border-blue-600 bg-blue-50 text-blue-900 shadow-sm":"border-slate-200 text-slate-700 hover:border-blue-400 hover:text-blue-700";return`<button type="button" data-slot-option="${n.start}" class="${i} ${u}">${c(n.label??Re(n))}</button>`}).join(""),s=o?`<div class="grid gap-2 sm:grid-cols-2">${o}</div>`:"",r=!e.slotsLoading&&!e.slots.length?`<p class="text-sm text-slate-500">${e.providerId&&e.serviceId?c(e.slotsError||"No open times for this day. Try another date."):"Select a provider and service to view appointments."}</p>`:"";return`
      <div>
        <div class="flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-900">Pick an available time</h2>
          ${e.selectedSlot?`<span class="text-sm text-slate-600">Selected: ${c(j(e.selectedSlot))}</span>`:""}
        </div>
        <div class="mt-3 space-y-3">
          ${t}
          ${s}
          ${r}
          ${v("slot_start",e.errors)}
        </div>
      </div>
    `}function Ce(){return`
      <div class="flex flex-col rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        <span class="font-semibold text-slate-700">Scheduling tips</span>
        <span>Only days with openings are shown. Need another time? Try a different provider or service.</span>
      </div>
    `}function Fe(e){const t=e.calendar??k(),o=t.availableDates??[],s=t.loading,r=!!(e.providerId&&e.serviceId);if(!o.length&&t.loading)return`
        <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-3 text-sm">
          <p class="font-medium text-slate-600">Preparing availability…</p>
          <p class="text-slate-500">We are checking the next 60 days for openings.</p>
        </div>
      `;if(!o.length&&t.error)return`
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
          <p class="font-semibold">Availability unavailable</p>
          <p>${c(t.error)}</p>
        </div>
      `;if(!o.length)return r?`
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
          <p class="font-semibold">No open days</p>
          <p>We could not find any openings in the next 60 days. Try another provider or service.</p>
        </div>
      `:`
          <div class="rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-600">
            <p class="font-semibold text-slate-700">Select provider & service</p>
            <p>Pick your provider and service to see available days.</p>
          </div>
        `;const n=o.map(u=>{const a=u===e.appointmentDate?"selected":"";return`<option value="${c(u)}" ${a}>${c(Oe(u))}</option>`}).join(""),l=o.slice(0,6).map(u=>{const a=u===e.appointmentDate,d="rounded-2xl border px-3 py-1.5 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200",b=a?"border-blue-600 bg-blue-50 text-blue-900":"border-slate-200 text-slate-700 hover:border-blue-400 hover:text-blue-700";return`<button type="button" data-date-pill="${c(u)}" class="${d} ${b}">${c(Ue(u))}</button>`}).join(""),i=Math.max(0,o.length-6);return`
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-2">Choose a day</label>
        <div class="flex flex-wrap gap-2 mb-3" role="listbox">
          ${l||'<span class="text-sm text-slate-500">No available days found.</span>'}
          ${i>0?`<span class="rounded-2xl border border-dashed border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-500">+${i} more days</span>`:""}
        </div>
        <label class="block text-sm font-medium text-slate-600">Browse all available days
          <select data-date-select class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" ${s?"disabled":""}>
            ${n}
          </select>
        </label>
        ${t.error?`<p class="mt-2 text-sm text-red-600">${c(t.error)}</p>`:""}
      </div>
    `}function Pe(e,t){const o=t.fieldConfig??{},r=["first_name","last_name","email","phone","address"].filter(l=>{var i;return((i=o[l])==null?void 0:i.display)!==!1});return r.length?`
      <div>
        <h2 class="text-base font-semibold text-slate-900">Your details</h2>
        <p class="text-sm text-slate-500">We will use this information to confirm your appointment and send reminders.</p>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
          ${r.map(l=>Te(l,o[l]??{},e)).join("")}
        </div>
      </div>
    `:""}function qe(e,t){const o=t.customFieldConfig??{},s=Object.keys(o);if(!s.length)return"";const r=s.map(n=>Be(n,o[n],e)).filter(Boolean).join("");return r?`
      <div>
        <h2 class="text-base font-semibold text-slate-900">Additional information</h2>
        <div class="mt-4 grid gap-4">
          ${r}
        </div>
      </div>
    `:""}function Ne(e,t){var r,n;const o=t.fieldConfig??{};return((r=o.notes)==null?void 0:r.display)===!1?"":`
      <div>
        <label class="block text-sm font-medium text-slate-700">
          Notes for your provider ${((n=o.notes)==null?void 0:n.required)?'<span class="text-red-500">*</span>':""}
          <textarea name="notes" rows="4" class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">${c(e.form.notes??"")}</textarea>
        </label>
        ${v("notes",e.errors)}
      </div>
    `}function Ae(e){const t=e.reschedulePolicy??{enabled:!0,label:"24 hours"};return`<p class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">${t.enabled?`Need to make a change? You can reschedule online up to ${c(t.label??"24 hours")} before your appointment.`:"Contact the office directly if you need to make a change."}</p>`}function je(e,t={}){const o=(t==null?void 0:t.submitLabel)??"Confirm appointment",s=(t==null?void 0:t.pendingLabel)??"Booking your appointment...",r=(t==null?void 0:t.helperText)??"We respect your privacy. Your confirmation token will be displayed and emailed immediately.",n=e.submitting?"disabled":"",l=e.submitting?s:o;return`
      <div class="flex flex-col gap-3">
        <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-transparent bg-blue-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-200 disabled:cursor-not-allowed disabled:bg-blue-300" ${n}>${c(l)}</button>
        <p class="text-center text-xs text-slate-400">${c(r)}</p>
      </div>
    `}function W(e,t,o={}){if(!e)return"";const s=X(e,t),r=z(e,t),n=V(e),l=o.title??"You're booked!",i=o.subtitle??"We'll send a confirmation email shortly. Keep your token handy if you need to make changes.",u=o.footerText??"Need to reschedule? Use your token and contact email to pull up this booking anytime.",a=o.primaryButton??{label:"Book another appointment",attr:"data-start-over"},d=o.secondaryButton,b=(a==null?void 0:a.attr)??"data-start-over",h=(d==null?void 0:d.attr)??"";return`
      <section class="rounded-3xl border border-slate-200 bg-white p-6 text-center shadow-sm">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
          <span class="text-2xl">&#10003;</span>
        </div>
        <h2 class="mt-4 text-2xl font-semibold text-slate-900">${c(l)}</h2>
        <p class="mt-2 text-sm text-slate-600">${c(i)}</p>
        <dl class="mt-6 grid gap-4 text-left md:grid-cols-2">
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Date & time</dt>
            <dd class="text-base font-semibold text-slate-900">${c(n)}</dd>
          </div>
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Provider</dt>
            <dd class="text-base font-semibold text-slate-900">${c(s)}</dd>
          </div>
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Service</dt>
            <dd class="text-base font-semibold text-slate-900">${c(r)}</dd>
          </div>
          <div class="rounded-2xl border border-slate-200 px-4 py-3">
            <dt class="text-sm font-medium text-slate-500">Confirmation token</dt>
            <dd class="text-base font-mono text-slate-900">${c(e.token??"")}</dd>
          </div>
        </dl>
        <div class="mt-6 flex flex-col gap-3">
          ${a?`<button type="button" ${b} class="inline-flex items-center justify-center rounded-2xl border border-slate-300 px-6 py-3 text-base font-semibold text-slate-700 transition hover:border-blue-500 hover:text-blue-600">${c(a.label)}</button>`:""}
          ${d?`<button type="button" ${h} class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-6 py-3 text-base font-semibold text-slate-700 transition hover:border-blue-400 hover:text-blue-600">${c(d.label)}</button>`:""}
          <p class="text-xs text-slate-500">${c(u)}</p>
        </div>
      </section>
    `}function Te(e,t,o){const s=t.label??Xe[e]??e,r=t.required;return`
      <label class="block text-sm font-medium text-slate-700">
        ${c(s)} ${r?'<span class="text-red-500">*</span>':""}
        <input name="${e}" value="${c(o.form[e]??"")}" type="${e==="email"?"email":e==="phone"?"tel":"text"}" class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" ${r?'aria-required="true"':""}>
        ${v(e,o.errors)}
      </label>
    `}function Be(e,t,o){if(!t||t.display===!1)return"";const s=t.title??`Custom field ${t.index}`;if(t.type==="textarea")return`
        <label class="block text-sm font-medium text-slate-700">
          ${c(s)} ${t.required?'<span class="text-red-500">*</span>':""}
          <textarea name="${e}" rows="3" class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">${c(o.form[e]??"")}</textarea>
          ${v(e,o.errors)}
        </label>
      `;if(t.type==="checkbox"){const r=(o.form[e]??"")==="1"?"checked":"";return`
        <label class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700">
          <span>${c(s)}</span>
          <input type="checkbox" name="${e}" class="h-4 w-4" ${r}>
        </label>
        ${v(e,o.errors)}
      `}return`
      <label class="block text-sm font-medium text-slate-700">
        ${c(s)} ${t.required?'<span class="text-red-500">*</span>':""}
        <input name="${e}" value="${c(o.form[e]??"")}" type="text" class="mt-1 w-full rounded-2xl border-slate-200 bg-white px-4 py-2.5 text-base text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
        ${v(e,o.errors)}
      </label>
    `}function v(e,t){return!t||!t[e]?"":`<p class="mt-1 text-sm text-red-600">${c(t[e])}</p>`}function Re(e){const t=e!=null&&e.start?new Date(e.start):null,o=e!=null&&e.end?new Date(e.end):null;if(!t||!o)return"Selected time";const s=new Intl.DateTimeFormat(void 0,{hour:"numeric",minute:"2-digit"});return`${s.format(t)} - ${s.format(o)}`}function j(e){if(!(e!=null&&e.start))return"";const t=new Date(e.start),o=e.end?new Date(e.end):null,s=new Intl.DateTimeFormat(void 0,{weekday:"short",month:"short",day:"numeric"}),r=new Intl.DateTimeFormat(void 0,{hour:"numeric",minute:"2-digit"}),n=s.format(t),l=o?`${r.format(t)} - ${r.format(o)}`:r.format(t);return`${n}, ${l}`}function V(e){return e?e.display_range?e.display_range:j({start:e.start,end:e.end}):""}function X(e,t){var s;if((s=e==null?void 0:e.provider)!=null&&s.name)return e.provider.name;const o=(t.providers??[]).find(r=>Number(r.id)===Number(e==null?void 0:e.provider_id));return(o==null?void 0:o.name)??(o==null?void 0:o.displayName)??"Assigned provider"}function z(e,t){var s;if((s=e==null?void 0:e.service)!=null&&s.name)return e.service.name;const o=(t.services??[]).find(r=>Number(r.id)===Number(e==null?void 0:e.service_id));return(o==null?void 0:o.name)??"Selected service"}function Me(){try{return JSON.parse(p.dataset.context??"{}")||window.__PUBLIC_BOOKING__||{}}catch(e){return console.error("[public-booking] Failed to parse context payload.",e),window.__PUBLIC_BOOKING__||{}}}function k(e=null){const t={availableDates:[],slotsByDate:{},startDate:null,endDate:null,timezone:null,generatedAt:null,defaultDate:null,loading:!1,error:""};if(!e||typeof e!="object")return{...t};const o=Array.isArray(e.availableDates)?[...e.availableDates]:[],s=e.slotsByDate&&typeof e.slotsByDate=="object"?e.slotsByDate:{},r=Object.keys(s).reduce((n,l)=>{const i=Array.isArray(s[l])?s[l].map(u=>({...u})):[];return n[l]=i,n},{});return{...t,availableDates:o,slotsByDate:r,startDate:e.start_date??e.startDate??null,endDate:e.end_date??e.endDate??null,timezone:e.timezone??null,generatedAt:e.generated_at??e.generatedAt??null,defaultDate:e.default_date??e.defaultDate??o[0]??null}}function J(e,t,o=null){var u,a;const s=Array.isArray(t.availableDates)?t.availableDates:[];if(!s.length)return{appointmentDate:e.appointmentDate,slots:[],selectedSlot:null,slotsError:t.error||"No availability found in the next 60 days.",prefetched:null};let r=o||e.appointmentDate;s.includes(r)||(r=s[0]);const n=Array.isArray((u=t.slotsByDate)==null?void 0:u[r])?t.slotsByDate[r]:[],l=(a=e.selectedSlot)==null?void 0:a.start,i=n.find(d=>d.start===l)??null;return{appointmentDate:r,slots:n,selectedSlot:i,slotsError:n.length===0?"No slots available for this date. Try another day.":"",prefetched:n.length?{date:r}:null}}function Y(e,t){if(!e)return"";const o=new Date(`${e}T00:00:00`);if(Number.isNaN(o.getTime()))return e;try{return new Intl.DateTimeFormat(void 0,t).format(o)}catch{return o.toLocaleDateString()}}function Oe(e){return Y(e,{weekday:"short",month:"short",day:"numeric"})}function Ue(e){return Y(e,{weekday:"short",month:"short",day:"numeric"})}function K(e){const t={first_name:"",last_name:"",email:"",phone:"",address:"",notes:""},o=e.fieldConfig??{};Object.keys(o).forEach(r=>{t[r]===void 0&&(t[r]="")});const s=e.customFieldConfig??{};return Object.keys(s).forEach(r=>{t[r]=s[r].type==="checkbox"?"0":""}),t}function G(e,t,o=null,s=null){var C,D,ee,te,oe,se,re;const r=((ee=(D=(C=e.providers)==null?void 0:C[0])==null?void 0:D.id)==null?void 0:ee.toString())??"",n=((se=(oe=(te=e.services)==null?void 0:te[0])==null?void 0:oe.id)==null?void 0:se.toString())??"",l=k(s),i=o&&String(o.provider_id??"")===r&&String(o.service_id??"")===n&&Array.isArray(o.slots)&&o.slots.length>0,u=l.availableDates[0]??null,a=i?o.date:null,d=u||a||t,b=((re=l.slotsByDate)==null?void 0:re[d])??[],h=b.length?b:i?o.slots:[];return!l.availableDates.length&&i&&(o!=null&&o.date)&&(l.availableDates=[o.date],l.slotsByDate={...l.slotsByDate,[o.date]:o.slots??[]}),{providerId:r,serviceId:n,services:e.services??[],servicesLoading:!1,appointmentDate:d,slots:h,slotsLoading:!1,slotsError:"",selectedSlot:null,prefetched:h.length?{date:d}:null,calendar:l,form:K(e),errors:{},globalError:"",submitting:!1,success:null}}function Q(e,t){return{stage:"lookup",lookupForm:{token:"",email:"",phone:""},lookupErrors:{},lookupError:"",lookupLoading:!1,appointment:null,success:null,contact:{email:"",phone:""},formState:{providerId:"",serviceId:"",services:e.services??[],servicesLoading:!1,appointmentDate:t,slots:[],slotsLoading:!1,slotsError:"",selectedSlot:null,calendar:k(),form:K(e),errors:{},globalError:"",submitting:!1}}}async function I(e){try{return await e.json()}catch{return null}}function He(e){return typeof structuredClone=="function"?structuredClone(e):JSON.parse(JSON.stringify(e))}function We(){const e=document.activeElement;return!e||!p.contains(e)?null:e instanceof HTMLInputElement||e instanceof HTMLTextAreaElement?{name:e.name||null,selectionStart:e.selectionStart,selectionEnd:e.selectionEnd}:null}function Ve(e){if(!(e!=null&&e.name))return;const t=`input[name="${e.name}"]`,o=p.querySelector(t)||p.querySelector(`textarea[name="${e.name}"]`);if(o instanceof HTMLInputElement||o instanceof HTMLTextAreaElement){o.focus({preventScroll:!0});const s=typeof e.selectionStart=="number"&&typeof e.selectionEnd=="number";try{if(s)o.setSelectionRange(e.selectionStart,e.selectionEnd);else{const r=o.value.length;o.setSelectionRange(r,r)}}catch{}}}}class F extends Error{constructor(P,$={}){super(P),this.details=$}}function c(g){return g==null?"":String(g).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;")}
