import{C as pe}from"./charts.js";import{D}from"./luxon.js";import{g as Ee,n as Te,b as Ae,f as $e,s as Ce,a as Fe,c as Me}from"./calendar-utils.js";function Z(){if(typeof Intl<"u"&&Intl.DateTimeFormat)try{const a=Intl.DateTimeFormat().resolvedOptions().timeZone;if(a)return a}catch(a){console.warn("[timezone-helper] Intl timezone detection failed:",a)}const i=X(),e=i>0?"-":"+",t=Math.floor(Math.abs(i)/60),n=Math.abs(i)%60,s=`UTC${e}${String(t).padStart(2,"0")}:${String(n).padStart(2,"0")}`;return console.warn("[timezone-helper] Using fallback timezone:",s),s}function X(){return new Date().getTimezoneOffset()}function ye(){const i=Z(),e=X();return window.__timezone={timezone:i,offset:e},{"X-Client-Timezone":i,"X-Client-Offset":e.toString()}}function ee(i,e="YYYY-MM-DD HH:mm:ss"){let t=i;typeof t=="string"&&!t.endsWith("Z")&&(t=t.replace(" ","T")+"Z");const n=new Date(t);if(isNaN(n.getTime()))return console.error("[timezone-helper] Invalid UTC datetime:",i),i;const s=n.getFullYear(),a=String(n.getMonth()+1).padStart(2,"0"),r=String(n.getDate()).padStart(2,"0"),o=String(n.getHours()).padStart(2,"0"),l=String(n.getMinutes()).padStart(2,"0"),d=String(n.getSeconds()).padStart(2,"0");return e.replace("YYYY",s).replace("MM",a).replace("DD",r).replace("HH",o).replace("mm",l).replace("ss",d)}function Ie(){const i=Z(),e=X();return{timezone:i,offset:e,logInfo(){console.group("%c[TIMEZONE DEBUG]","color: blue; font-weight: bold; font-size: 14px"),console.log("Browser Timezone:",this.timezone),console.log("UTC Offset:",this.offset,"minutes"),console.log("Offset (hours):",`UTC${this.offset>0?"+":"-"}${Math.abs(this.offset/60)}`),console.log("Current Local Time:",new Date().toString()),console.log("Current UTC Time:",new Date().toUTCString()),console.groupEnd()},logEvent(t){console.group(`%c[TIMEZONE DEBUG] Event: ${t.title||t.id}`,"color: green; font-weight: bold"),console.log("Event ID:",t.id),console.log("Start (UTC):",t.startStr||t.start),console.log("Start (Local):",ee(t.startStr||t.start)),console.log("End (UTC):",t.endStr||t.end),console.log("End (Local):",ee(t.endStr||t.end)),console.log("Duration:",Math.round((new Date(t.endStr||t.end)-new Date(t.startStr||t.start))/6e4),"minutes"),console.log("Browser Timezone:",this.timezone),console.log("UTC Offset:",this.offset,"minutes"),console.groupEnd()},logTime(t){const n=ee(t);console.group("%c[TIMEZONE DEBUG] Time Conversion","color: orange; font-weight: bold"),console.log("UTC:",t),console.log("Local:",n),console.log("Timezone:",this.timezone),console.groupEnd()},compare(t,n){console.group("%c[TIMEZONE DEBUG] Time Mismatch Check","color: red; font-weight: bold"),console.log("Expected (Local):",n),console.log("Actual (Local):",t);const s=t===n;console.log("Match:",s?"✅ YES":"❌ NO"),s||console.log("⚠️ MISMATCH DETECTED - Check timezone conversion"),console.groupEnd()}}}function Le(){window.DEBUG_TIMEZONE=Ie()}typeof window<"u"&&window.location.hostname==="localhost"&&Le();async function Be(){const i=document.querySelector('form[action*="/appointments/store"], form[action*="/appointments/update"]');if(!i)return;const e=document.getElementById("provider_id"),t=document.getElementById("service_id"),n=document.getElementById("appointment_date"),s=document.getElementById("appointment_time");if(fe(i),!e||!t||!n||!s){console.warn("Appointment form elements not found");return}const a={provider_id:null,service_id:null,date:null,time:null,duration:null,isChecking:!1,isAvailable:null};t.disabled=!0,t.classList.add("bg-gray-100","dark:bg-gray-800","cursor-not-allowed");const r=Ne();s.parentNode.appendChild(r);const o=qe();s.parentNode.appendChild(o),e.addEventListener("change",async function(){const l=this.value;if(a.provider_id=l,!l){t.disabled=!0,t.innerHTML='<option value="">Select a provider first...</option>',t.classList.add("bg-gray-100","dark:bg-gray-800","cursor-not-allowed"),ae();return}await He(l,t),a.service_id=null,ae()}),t.addEventListener("change",function(){const l=this.options[this.selectedIndex];a.service_id=this.value,this.value?(a.duration=parseInt(l.dataset.duration)||0,he(a,o)):(a.duration=null,se(o)),te(a,r)}),n.addEventListener("change",function(){a.date=this.value,te(a,r)}),s.addEventListener("change",function(){a.time=this.value,he(a,o),te(a,r)}),i.addEventListener("submit",async function(l){if(l.preventDefault(),a.isAvailable===!1)return alert("This time slot is not available. Please choose a different time."),!1;if(a.isChecking)return alert("Please wait while we check availability..."),!1;const d=i.querySelector('button[type="submit"]'),c=d.textContent;try{d.disabled=!0,d.textContent="⏳ Creating appointment...";const h=new FormData(i),u=i.getAttribute("action"),f=await fetch(u,{method:"POST",headers:{...ye(),"X-Requested-With":"XMLHttpRequest"},body:h});if(!f.ok){const g=await f.text();throw console.error("[appointments-form] Server error response:",g),new Error(`Server returned ${f.status}`)}const p=f.headers.get("content-type");if(p&&p.includes("application/json")){const g=await f.json();if(g.success||g.data){if(alert("✅ Appointment booked successfully!"),typeof window<"u"){const v={source:"appointment-form",action:"create-or-update"};typeof window.emitAppointmentsUpdated=="function"?window.emitAppointmentsUpdated(v):window.dispatchEvent(new CustomEvent("appointments-updated",{detail:v}))}window.location.href="/appointments"}else throw new Error(g.error||"Unknown error occurred")}else{if(typeof window<"u"){const g={source:"appointment-form",action:"create-or-update"};typeof window.emitAppointmentsUpdated=="function"?window.emitAppointmentsUpdated(g):window.dispatchEvent(new CustomEvent("appointments-updated",{detail:g}))}window.location.href="/appointments"}}catch(h){console.error("[appointments-form] ❌ Form submission error:",h),alert("❌ Failed to create appointment: "+h.message),d.disabled=!1,d.textContent=c}return!1})}async function He(i,e,t){try{e.disabled=!0,e.classList.add("bg-gray-100","dark:bg-gray-800"),e.innerHTML='<option value="">🔄 Loading services...</option>';const n=await fetch(`/api/v1/providers/${i}/services`,{method:"GET",headers:{...ye(),Accept:"application/json","X-Requested-With":"XMLHttpRequest"}});if(!n.ok)throw new Error(`HTTP ${n.status}`);const s=await n.json();if(s.data&&Array.isArray(s.data)&&s.data.length>0){const a=s.data;e.innerHTML='<option value="">Select a service...</option>',a.forEach(r=>{const o=document.createElement("option");o.value=r.id,o.textContent=`${r.name} - ${r.duration} min - $${parseFloat(r.price).toFixed(2)}`,o.dataset.duration=r.duration,o.dataset.price=r.price,e.appendChild(o)}),e.disabled=!1,e.classList.remove("bg-gray-100","dark:bg-gray-800","cursor-not-allowed")}else e.innerHTML='<option value="">No services available for this provider</option>',e.disabled=!0}catch(n){console.error("Error loading provider services:",n),e.innerHTML='<option value="">⚠️ Error loading services. Please try again.</option>',e.disabled=!0,setTimeout(()=>{e.innerHTML='<option value="">Select a provider first...</option>'},3e3)}}async function te(i,e){if(!i.provider_id||!i.service_id||!i.date||!i.time||!i.duration){ae(e);return}i.isChecking=!0,_e(e);try{const t=`${i.date} ${i.time}:00`,n=new Date(`${i.date}T${i.time}:00`),a=new Date(n.getTime()+i.duration*6e4).toISOString().slice(0,19).replace("T"," "),r=await fetch("/api/availability/check",{method:"POST",headers:{"Content-Type":"application/json",Accept:"application/json","X-Requested-With":"XMLHttpRequest"},body:JSON.stringify({provider_id:parseInt(i.provider_id),start_time:t,end_time:a,timezone:Z()})});if(!r.ok)throw new Error(`HTTP ${r.status}`);const o=await r.json(),l=o.data||o;if(i.isAvailable=l.available===!0,i.isAvailable)Oe(e,"✓ Time slot available");else{const d=l.reason||"Time slot not available";Pe(e,d)}}catch(t){console.error("Error checking availability:",t),i.isAvailable=null,ze(e,"Unable to verify availability")}finally{i.isChecking=!1}}function he(i,e){if(!i.time||!i.duration){se(e);return}try{const[t,n]=i.time.split(":").map(Number),s=new Date;s.setHours(t,n,0,0);const a=new Date(s.getTime()+i.duration*6e4),r=String(a.getHours()).padStart(2,"0"),o=String(a.getMinutes()).padStart(2,"0"),l=`${r}:${o}`;e.textContent=`Ends at: ${l}`,e.classList.remove("hidden")}catch(t){console.error("Error calculating end time:",t),se(e)}}function se(i){i.textContent="",i.classList.add("hidden")}function ae(i){i&&(i.textContent="",i.className="mt-2 text-sm hidden")}function fe(i){const e=i==null?void 0:i.querySelector("#client_timezone"),t=i==null?void 0:i.querySelector("#client_offset");e&&(e.value=Z()),t&&(t.value=X())}typeof document<"u"&&document.addEventListener("visibilitychange",()=>{if(!document.hidden){const i=document.querySelector('form[action*="/appointments/store"]');i&&fe(i)}});function _e(i){i.textContent="Checking availability...",i.className="mt-2 text-sm text-gray-600 dark:text-gray-400"}function Oe(i,e){i.innerHTML=`
        <span class="inline-flex items-center">
            <span class="material-symbols-outlined text-base mr-1">check_circle</span>
            ${e}
        </span>
    `,i.className="mt-2 text-sm text-green-600 dark:text-green-400"}function Pe(i,e){i.innerHTML=`
        <span class="inline-flex items-center">
            <span class="material-symbols-outlined text-base mr-1">cancel</span>
            ${e}
        </span>
    `,i.className="mt-2 text-sm text-red-600 dark:text-red-400"}function ze(i,e){i.innerHTML=`
        <span class="inline-flex items-center">
            <span class="material-symbols-outlined text-base mr-1">warning</span>
            ${e}
        </span>
    `,i.className="mt-2 text-sm text-amber-600 dark:text-amber-400"}function Ne(){const i=document.createElement("div");return i.className="mt-2 text-sm hidden",i.setAttribute("role","status"),i.setAttribute("aria-live","polite"),i}function qe(){const i=document.createElement("div");return i.className="mt-2 text-sm text-gray-600 dark:text-gray-400 hidden",i}const Ue={pending:{bg:"#FEF3C7",border:"#F59E0B",text:"#78350F",dot:"#F59E0B"},confirmed:{bg:"#DBEAFE",border:"#3B82F6",text:"#1E3A8A",dot:"#3B82F6"},completed:{bg:"#D1FAE5",border:"#10B981",text:"#064E3B",dot:"#10B981"},cancelled:{bg:"#FEE2E2",border:"#EF4444",text:"#7F1D1D",dot:"#EF4444"},"no-show":{bg:"#F3F4F6",border:"#6B7280",text:"#1F2937",dot:"#6B7280"}},je={pending:{bg:"#78350F",border:"#F59E0B",text:"#FEF3C7",dot:"#F59E0B"},confirmed:{bg:"#1E3A8A",border:"#3B82F6",text:"#DBEAFE",dot:"#3B82F6"},completed:{bg:"#064E3B",border:"#10B981",text:"#D1FAE5",dot:"#10B981"},cancelled:{bg:"#7F1D1D",border:"#EF4444",text:"#FEE2E2",dot:"#EF4444"},"no-show":{bg:"#374151",border:"#9CA3AF",text:"#F3F4F6",dot:"#9CA3AF"}};function R(i,e=!1){const t=(i==null?void 0:i.toLowerCase())||"pending",n=e?je:Ue;return n[t]||n.pending}function G(i){return(i==null?void 0:i.color)||"#3B82F6"}function W(){return document.documentElement.classList.contains("dark")||window.matchMedia("(prefers-color-scheme: dark)").matches}const Ve={pending:"Pending",confirmed:"Confirmed",completed:"Completed",cancelled:"Cancelled","no-show":"No Show"};function Re(i){return Ve[i==null?void 0:i.toLowerCase()]||"Unknown"}const ge=()=>{try{if(typeof window<"u"&&typeof window.__SCHEDULER_DEBUG__<"u")return!!window.__SCHEDULER_DEBUG__;if(typeof localStorage<"u"){const i=localStorage.getItem("scheduler:debug");return i==="1"||i==="true"}}catch{}return!1},Y="[Scheduler]",m={debug:(...i)=>{ge()&&console.debug(Y,...i)},info:(...i)=>{ge()&&console.info(Y,...i)},warn:(...i)=>console.warn(Y,...i),error:(...i)=>console.error(Y,...i),enable:(i=!0)=>{try{typeof window<"u"&&(window.__SCHEDULER_DEBUG__=!!i)}catch{}}};class We{constructor(e){this.scheduler=e,this.appointmentsByDate={},this.selectedDate=null}render(e,t){const{currentDate:n,appointments:s,providers:a,config:r,settings:o}=t;m.debug("🗓️ MonthView.render called"),m.debug("   Current date:",n.toISO()),m.debug("   Appointments received:",s.length),m.debug("   Appointments data:",s),m.debug("   Providers:",a.length),this.appointments=s,this.providers=a,this.settings=o,this.blockedPeriods=(r==null?void 0:r.blockedPeriods)||[],this.currentDate=n,this.selectedDate||(this.selectedDate=D.now().setZone(this.scheduler.options.timezone));const l=n.startOf("month"),d=n.endOf("month"),c=(o==null?void 0:o.getFirstDayOfWeek())||0;let h=l.startOf("week");c===0&&(h=h.minus({days:1}));let u=d.endOf("week");c===0&&(u=u.minus({days:1}));const f=[];let p=h;for(;p<=u;){const g=[];for(let v=0;v<7;v++)g.push(p),p=p.plus({days:1});f.push(g)}this.appointmentsByDate=this.groupAppointmentsByDate(s),e.innerHTML=`
            <div class="scheduler-month-view bg-white dark:bg-gray-800">
                <!-- Day Headers -->
                <div class="grid grid-cols-7 border-b border-gray-200 dark:border-gray-700">
                    ${this.renderDayHeaders(r,o)}
                </div>

                <!-- Calendar Grid - P0-3 FIX: Use minmax for proper row sizing -->
                <div class="grid grid-cols-7 auto-rows-[minmax(100px,auto)] divide-x divide-y divide-gray-200 dark:divide-gray-700">
                    ${f.map(g=>g.map(v=>this.renderDayCell(v,l.month,o)).join("")).join("")}
                </div>
                
                ${s.length===0?`
                <div class="px-6 py-8 text-center border-t border-gray-200 dark:border-gray-700">
                    <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-5xl mb-3">event_available</span>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Appointments</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Click on any day to create a new appointment
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-500">
                        💡 Backend API endpoints need to be implemented to load and save appointments
                    </p>
                </div>
                `:""}
            </div>
        `,this.attachEventListeners(e,t),this.renderDailySection(t)}renderDayHeaders(e,t){const n=(t==null?void 0:t.getFirstDayOfWeek())||(e==null?void 0:e.firstDayOfWeek)||0,s=["Sun","Mon","Tue","Wed","Thu","Fri","Sat"];return[...s.slice(n),...s.slice(0,n)].map(r=>`
            <div class="px-4 py-3 text-center">
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">${r}</span>
            </div>
        `).join("")}renderDayCell(e,t,n){const s=e.hasSame(D.now(),"day"),a=e.month===t,r=e<D.now().startOf("day"),o=this.getAppointmentsForDay(e),l=n!=null&&n.isWorkingDay?n.isWorkingDay(e):!0,d=this.isDateBlocked(e),c=d?this.getBlockedPeriodInfo(e):null,h=this.selectedDate&&e.hasSame(this.selectedDate,"day");return`
            <div class="${["scheduler-day-cell","min-h-[100px]","p-2","relative","cursor-pointer","hover:bg-gray-50","dark:hover:bg-gray-700/50","transition-colors",s?"today":"",a?"":"other-month",r?"past":"",l?"":"non-working-day",d?"bg-red-50 dark:bg-red-900/10":"",h?"ring-2 ring-blue-500 ring-inset bg-blue-50 dark:bg-blue-900/20":""].filter(Boolean).join(" ")}" data-date="${e.toISODate()}" data-click-create="day" data-select-day="${e.toISODate()}">
                <div class="day-number text-sm font-medium mb-1 ${a?d?"text-red-600 dark:text-red-400":"text-gray-900 dark:text-white":"text-gray-400 dark:text-gray-600"}">
                    ${e.day}
                    ${d?'<span class="text-xs ml-1">🚫</span>':""}
                </div>
                ${d&&c?`
                    <div class="text-[10px] text-red-600 dark:text-red-400 font-medium mb-1 truncate" title="${this.escapeHtml(c.notes||"Blocked")}">
                        ${this.escapeHtml(c.notes||"Blocked")}
                    </div>
                `:""}
                <div class="day-appointments space-y-1">
                    ${o.slice(0,3).map(p=>this.renderAppointmentBlock(p)).join("")}
                    ${o.length>3?`<div class="text-xs text-gray-500 dark:text-gray-400 font-medium cursor-pointer hover:text-blue-600" data-show-more="${e.toISODate()}">+${o.length-3} more</div>`:""}
                </div>
            </div>
        `}renderAppointmentBlock(e){var t;try{const n=this.providers.find(c=>c.id===e.providerId),s=W(),a=R(e.status,s),r=G(n),o=(t=this.settings)!=null&&t.formatTime?this.settings.formatTime(e.startDateTime):e.startDateTime.toFormat("h:mm a"),l=e.title||e.customerName||"Appointment";return`
            <div class="scheduler-appointment text-xs px-2 py-1 rounded cursor-pointer hover:opacity-90 transition-all truncate border-l-4 flex items-center gap-1.5"
                 style="background-color: ${a.bg}; border-left-color: ${a.border}; color: ${a.text};"
                 data-appointment-id="${e.id}"
                 title="${l} at ${o} - ${e.status}">
                <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background-color: ${r};" title="${(n==null?void 0:n.name)||"Provider"}"></span>
                <span class="font-medium">${o}</span>
                <span class="truncate">${this.escapeHtml(l)}</span>
            </div>
        `}catch(n){return console.error(`Error rendering appointment #${e.id}:`,n),'<div class="text-red-500">Error rendering appointment</div>'}}getAppointmentsForDay(e){const t=e.toISODate();return this.appointmentsByDate[t]||[]}groupAppointmentsByDate(e){const t={};return e.forEach(n=>{if(!n.startDateTime){console.error("Appointment missing startDateTime:",n);return}const s=n.startDateTime.toISODate();t[s]||(t[s]=[]),t[s].push(n)}),Object.keys(t).forEach(n=>{t[n].sort((s,a)=>s.startDateTime.toMillis()-a.startDateTime.toMillis())}),m.debug("🗂️ Final grouped appointments:",Object.keys(t).map(n=>`${n}: ${t[n].length} appointments`)),t}renderDailyAppointments(){var l;const e=this.currentDate.startOf("month"),t=this.currentDate.endOf("month"),n=this.appointments.filter(d=>d.startDateTime>=e&&d.startDateTime<=t),s={};this.providers.forEach(d=>{s[d.id]=[]}),n.forEach(d=>{s[d.providerId]&&s[d.providerId].push(d)});const a=this.providers.filter(d=>s[d.id].length>0),r=((l=this.settings)==null?void 0:l.getTimeFormat())==="24h"?"HH:mm":"h:mm a";let o=`
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Monthly Schedule
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            ${this.currentDate.toFormat("MMMM yyyy")}
                        </p>
                    </div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        ${n.length} ${n.length===1?"appointment":"appointments"} this month
                    </span>
                </div>
            </div>
        `;return a.length>0?(o+=`
                <!-- Provider Columns -->
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-${Math.min(a.length,3)} gap-4">
            `,a.forEach(d=>{const c=s[d.id]||[],h=d.color||"#3B82F6";c.sort((g,v)=>g.startDateTime.toMillis()-v.startDateTime.toMillis());const u=10,f=c.length>u,p=d.id;if(o+=`
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden" data-provider-card="${p}">
                        <!-- Provider Header -->
                        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700"
                             style="border-left: 4px solid ${h};">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-semibold text-sm"
                                     style="background-color: ${h};">
                                    ${d.name.charAt(0).toUpperCase()}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-medium text-gray-900 dark:text-white truncate">
                                        ${this.escapeHtml(d.name)}
                                    </h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        ${c.length} ${c.length===1?"appointment":"appointments"} this month
                                    </p>
                                </div>
                                ${f?`
                                <button type="button" 
                                        class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium flex items-center gap-1 transition-colors"
                                        data-expand-provider="${p}"
                                        title="View all ${c.length} appointments">
                                    <span class="material-symbols-outlined text-sm">expand_more</span>
                                    <span>View all</span>
                                </button>
                                `:""}
                            </div>
                        </div>
                        
                        <!-- Appointments List - P0-4 FIX: Scrollable container with all appointments -->
                        <div class="divide-y divide-gray-200 dark:divide-gray-700 overflow-y-auto transition-all duration-300"
                             data-provider-appointments="${p}"
                             style="max-height: ${f?"400px":"none"};">
                `,c.length>0){if(c.forEach((g,v)=>{const S=g.startDateTime.toFormat("MMM d"),b=g.startDateTime.toFormat(r),y=g.name||g.customerName||g.title||"Unknown",I=g.serviceName||"Appointment",C=W(),L=R(g.status,C),P=G(d),_=f&&v>=u;o+=`
                            <div class="p-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer border-l-4 ${_?"hidden":""}"
                                 style="border-left-color: ${L.border}; background-color: ${L.bg}; color: ${L.text};"
                                 data-appointment-id="${g.id}"
                                 data-provider-apt="${p}"
                                 data-apt-index="${v}">
                                <div class="flex items-start justify-between gap-2 mb-1">
                                    <div class="flex-1 min-w-0 flex items-center gap-2">
                                        <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background-color: ${P};"></span>
                                        <div class="text-xs font-medium">
                                            ${S} • ${b}
                                        </div>
                                    </div>
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full flex-shrink-0"
                                          style="background-color: ${L.dot}; color: white;">
                                        ${g.status}
                                    </span>
                                </div>
                                <h5 class="font-semibold text-sm mb-1 truncate">
                                    ${this.escapeHtml(y)}
                                </h5>
                                <p class="text-xs opacity-80 truncate">
                                    ${this.escapeHtml(I)}
                                </p>
                            </div>
                        `}),f){const g=c.length-u;o+=`
                            <div class="p-3 text-center border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 sticky bottom-0"
                                 data-expand-footer="${p}">
                                <button type="button"
                                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-all"
                                        data-expand-toggle="${p}"
                                        data-expanded="false">
                                    <span class="material-symbols-outlined text-lg expand-icon">expand_more</span>
                                    <span class="expand-text">Show ${g} more appointments</span>
                                </button>
                            </div>
                        `}}else o+=`
                        <div class="p-8 text-center">
                            <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-4xl mb-2">event_available</span>
                            <p class="text-sm text-gray-500 dark:text-gray-400">No appointments</p>
                        </div>
                    `;o+=`
                        </div>
                    </div>
                `}),o+=`
                    </div>
                </div>
            `):o+=`
                <!-- Empty State -->
                <div class="p-12 text-center">
                    <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-5xl mb-3">event_available</span>
                    <p class="text-sm text-gray-600 dark:text-gray-400">No appointments scheduled for ${this.currentDate.toFormat("MMMM yyyy")}</p>
                </div>
            `,o}renderDailySection(e){const t=document.getElementById("daily-provider-appointments");if(!t){m.debug("[MonthView] Daily provider appointments container not found");return}m.debug("[MonthView] Rendering daily section to separate container"),t.innerHTML=this.renderDailyAppointments(),this.attachDailySectionListeners(t,e)}attachDailySectionListeners(e,t){if(!e)return;const n=(t==null?void 0:t.appointments)||this.appointments,s=(t==null?void 0:t.onAppointmentClick)||this.scheduler.handleAppointmentClick.bind(this.scheduler);e.querySelectorAll("[data-action]").forEach(a=>{a.addEventListener("click",r=>{r.preventDefault(),r.stopPropagation();const o=a.dataset.action,l=parseInt(a.dataset.appointmentId,10),d=n.find(c=>c.id===l);d&&(o==="view"||o==="edit")&&s(d)})}),e.querySelectorAll("[data-appointment-id]:not([data-action])").forEach(a=>{a.addEventListener("click",r=>{if(r.target.closest("[data-action]"))return;const o=parseInt(a.dataset.appointmentId,10),l=n.find(d=>d.id===o);l&&s(l)})}),e.querySelectorAll("[data-expand-toggle]").forEach(a=>{a.addEventListener("click",r=>{r.preventDefault(),r.stopPropagation();const o=a.dataset.expandToggle,l=a.dataset.expanded==="true",d=e.querySelector(`[data-provider-appointments="${o}"]`),c=a.querySelector(".expand-icon"),h=a.querySelector(".expand-text");if(!d)return;const u=d.querySelectorAll(`[data-provider-apt="${o}"].hidden`);if(d.querySelectorAll(`[data-provider-apt="${o}"]:not(.hidden)`),l){d.querySelectorAll(`[data-provider-apt="${o}"]`).forEach(g=>{parseInt(g.dataset.aptIndex,10)>=10&&g.classList.add("hidden")}),a.dataset.expanded="false",c&&(c.textContent="expand_more");const p=d.querySelectorAll(`[data-provider-apt="${o}"]`).length-10;h&&(h.textContent=`Show ${p} more appointments`),d.style.maxHeight="400px",d.scrollTop=0}else u.forEach(f=>{f.classList.remove("hidden")}),a.dataset.expanded="true",c&&(c.textContent="expand_less"),h&&(h.textContent="Show less"),d.style.maxHeight="600px";m.debug(`[MonthView] Provider ${o} appointments ${l?"collapsed":"expanded"}`)})}),e.querySelectorAll("[data-expand-provider]").forEach(a=>{a.addEventListener("click",r=>{r.preventDefault(),r.stopPropagation();const o=a.dataset.expandProvider,l=e.querySelector(`[data-expand-toggle="${o}"]`);l&&l.dataset.expanded!=="true"&&l.click()})})}attachEventListeners(e,t){e.querySelectorAll(".scheduler-appointment").forEach(n=>{n.addEventListener("click",s=>{s.preventDefault(),s.stopPropagation(),m.debug("[MonthView] Appointment clicked, prevented default");const a=parseInt(n.dataset.appointmentId,10),r=t.appointments.find(o=>o.id===a);r&&t.onAppointmentClick?(m.debug("[MonthView] Calling onAppointmentClick"),t.onAppointmentClick(r)):m.warn("[MonthView] No appointment found or no callback")})}),e.querySelectorAll("[data-show-more]").forEach(n=>{n.addEventListener("click",s=>{s.stopPropagation();const a=n.dataset.showMore;this.selectedDate=D.fromISO(a,{zone:this.scheduler.options.timezone});const r=this.getAppointmentsForDay(this.selectedDate);this.showDayAppointmentsModal(this.selectedDate,r,t),m.debug("Show more appointments for",a,"- Total:",r.length)})}),e.querySelectorAll("[data-select-day]").forEach(n=>{n.addEventListener("click",s=>{if(s.target.closest(".scheduler-appointment")||s.target.closest("[data-action]")||s.target.closest("[data-show-more]"))return;const a=n.dataset.selectDay;m.debug("Day cell clicked:",a),this.selectedDate=D.fromISO(a,{zone:this.scheduler.options.timezone}),e.querySelectorAll(".scheduler-day-cell").forEach(r=>{r.classList.remove("ring-2","ring-blue-500","ring-inset","bg-blue-50","dark:bg-blue-900/20")}),n.classList.add("ring-2","ring-blue-500","ring-inset","bg-blue-50","dark:bg-blue-900/20"),this.updateDailySection(e)})})}updateDailySection(e){const t={appointments:this.appointments,onAppointmentClick:this.scheduler.handleAppointmentClick.bind(this.scheduler)};this.renderDailySection(t)}getContrastColor(e){const t=e.replace("#",""),n=parseInt(t.substr(0,2),16),s=parseInt(t.substr(2,2),16),a=parseInt(t.substr(4,2),16);return(.299*n+.587*s+.114*a)/255>.5?"#000000":"#FFFFFF"}isDateBlocked(e){if(!this.blockedPeriods||this.blockedPeriods.length===0)return!1;const t=e.toISODate();return this.blockedPeriods.some(n=>{const s=n.start,a=n.end;return t>=s&&t<=a})}getBlockedPeriodInfo(e){if(!this.blockedPeriods||this.blockedPeriods.length===0)return null;const t=e.toISODate();return this.blockedPeriods.find(s=>t>=s.start&&t<=s.end)||null}showDayAppointmentsModal(e,t,n){var h;const s=((h=this.settings)==null?void 0:h.getTimeFormat())==="24h"?"HH:mm":"h:mm a",a=e.toFormat("EEEE, MMMM d, yyyy"),r=(n==null?void 0:n.onAppointmentClick)||this.scheduler.handleAppointmentClick.bind(this.scheduler),o=document.getElementById("day-appointments-modal");o&&o.remove();const l=`
            <div id="day-appointments-modal" class="scheduler-modal" role="dialog" aria-modal="true" aria-labelledby="day-modal-title">
                <div class="scheduler-modal-backdrop" data-close-modal></div>
                <div class="scheduler-modal-dialog">
                    <div class="scheduler-modal-panel">
                        <div class="scheduler-modal-header">
                            <div>
                                <h3 id="day-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white">
                                    ${a}
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    ${t.length} ${t.length===1?"appointment":"appointments"}
                                </p>
                            </div>
                            <button type="button" class="p-2 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700 transition-colors" data-close-modal>
                                <span class="material-symbols-outlined">close</span>
                            </button>
                        </div>
                        <div class="scheduler-modal-body">
                            ${t.length>0?`
                                <div class="divide-y divide-gray-200 dark:divide-gray-700 max-h-96 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                    ${t.map(u=>{const f=u.startDateTime.toFormat(s),p=u.endDateTime?u.endDateTime.toFormat(s):"",g=u.name||u.customerName||u.title||"Unknown",v=u.serviceName||"Appointment",S=this.providers.find(L=>L.id===u.providerId),b=(S==null?void 0:S.name)||"Unknown Provider",y=(S==null?void 0:S.color)||"#3B82F6",I=W(),C=R(u.status,I);return`
                                            <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer"
                                                 data-modal-appointment-id="${u.id}">
                                                <div class="flex items-start gap-3">
                                                    <div class="flex-shrink-0 w-1 h-full rounded-full" style="background-color: ${y};"></div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center justify-between gap-2 mb-2">
                                                            <div class="flex items-center gap-2">
                                                                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                                                                    ${f}${p?` - ${p}`:""}
                                                                </span>
                                                                <span class="px-2 py-0.5 text-xs font-medium rounded-full"
                                                                      style="background-color: ${C.bg}; color: ${C.text}; border: 1px solid ${C.border};">
                                                                    ${u.status}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <h4 class="font-medium text-gray-900 dark:text-white mb-1">
                                                            ${this.escapeHtml(g)}
                                                        </h4>
                                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                                                            ${this.escapeHtml(v)}
                                                        </p>
                                                        <p class="text-xs text-gray-500 dark:text-gray-500 flex items-center gap-1">
                                                            <span class="inline-block w-2 h-2 rounded-full" style="background-color: ${y};"></span>
                                                            ${this.escapeHtml(b)}
                                                        </p>
                                                    </div>
                                                    <button type="button" class="p-2 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors" title="View details">
                                                        <span class="material-symbols-outlined text-lg">chevron_right</span>
                                                    </button>
                                                </div>
                                            </div>
                                        `}).join("")}
                                </div>
                            `:`
                                <div class="p-8 text-center">
                                    <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-5xl mb-3">event_available</span>
                                    <p class="text-gray-500 dark:text-gray-400">No appointments scheduled for this day</p>
                                </div>
                            `}
                        </div>
                        <div class="scheduler-modal-footer">
                            <button type="button" class="btn btn-secondary" data-close-modal>
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;document.body.insertAdjacentHTML("beforeend",l);const d=document.getElementById("day-appointments-modal");requestAnimationFrame(()=>{d.classList.add("scheduler-modal-open")}),d.querySelectorAll("[data-close-modal]").forEach(u=>{u.addEventListener("click",()=>{this.closeDayAppointmentsModal()})});const c=u=>{u.key==="Escape"&&(this.closeDayAppointmentsModal(),document.removeEventListener("keydown",c))};document.addEventListener("keydown",c),d.querySelectorAll("[data-modal-appointment-id]").forEach(u=>{u.addEventListener("click",()=>{const f=parseInt(u.dataset.modalAppointmentId,10),p=t.find(g=>g.id===f);p&&(this.closeDayAppointmentsModal(),r(p))})})}closeDayAppointmentsModal(){const e=document.getElementById("day-appointments-modal");e&&(e.classList.remove("scheduler-modal-open"),setTimeout(()=>{e.remove()},250))}escapeHtml(e){const t=document.createElement("div");return t.textContent=e,t.innerHTML}}function Ye(i,e,t="12h"){if(t==="24h")return`${i.toString().padStart(2,"0")}:${e.toString().padStart(2,"0")}`;const n=i>=12?"PM":"AM";return`${i%12===0?12:i%12}:${e.toString().padStart(2,"0")} ${n}`}function be(i,e="12h",t=60){const n=[],s=(o,l="09:00")=>{const d=o||l,[c,h]=d.split(":").map(u=>parseInt(u,10));return c*60+(h||0)},a=s(i==null?void 0:i.startTime,"09:00"),r=s(i==null?void 0:i.endTime,"17:00");for(let o=a;o+t<=r;o+=t){const l=Math.floor(o/60),d=o%60,c=`${l.toString().padStart(2,"0")}:${d.toString().padStart(2,"0")}`,h=Ye(l,d,e);n.push({time:c,display:h,hour:l,minute:d})}return n}function H(i){const e=document.createElement("div");return e.textContent=i??"",e.innerHTML}function ie(i,e){if(!e||e.length===0)return!1;const t=i.toISODate();return e.some(n=>t>=n.start&&t<=n.end)}function Ge(i,e){if(!e||e.length===0)return null;const t=i.toISODate();return e.find(n=>t>=n.start&&t<=n.end)||null}class Ze{constructor(e){this.scheduler=e}render(e,t){var v,S;const{currentDate:n,appointments:s,providers:a,config:r}=t,o=n.startOf("week");o.plus({days:6});const l=[];for(let b=0;b<7;b++)l.push(o.plus({days:b}));const d=(r==null?void 0:r.blockedPeriods)||[],c=(r==null?void 0:r.slotMinTime)||"08:00",h=(r==null?void 0:r.slotMaxTime)||"17:00",u={startTime:c,endTime:h},f=((S=(v=this.scheduler)==null?void 0:v.settingsManager)==null?void 0:S.getTimeFormat())||"12h",p=be(u,f,30),g=this.groupAppointmentsByDay(s,o);e.innerHTML=`
            <div class="scheduler-week-view bg-white dark:bg-gray-800">
                <!-- Calendar Grid -->
                <div class="overflow-x-auto">
                    <div class="inline-block min-w-full">
                        <!-- Day Headers -->
                        <div class="grid grid-cols-8 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                            <div class="px-4 py-3 text-center border-r border-gray-200 dark:border-gray-700">
                                <span class="text-sm font-semibold text-gray-500 dark:text-gray-400">Time</span>
                            </div>
                            ${l.map(b=>this.renderDayHeader(b,d)).join("")}
                        </div>

                        <!-- Time Grid -->
                        <div class="relative">
                            ${p.map((b,y)=>this.renderTimeSlot(b,y,l,g,a,t,d)).join("")}
                        </div>
                    </div>
                </div>
            </div>
        `,this.attachEventListeners(e,t),this.renderWeeklyAppointmentsSection(l,s,a,t)}renderDayHeader(e,t){const n=e.hasSame(D.now(),"day"),s=ie(e,t);return s&&Ge(e,t),`
            <div class="px-4 py-3 text-center border-r border-gray-200 dark:border-gray-700 last:border-r-0 ${s?"bg-red-50 dark:bg-red-900/10":""}">
                <div class="${n?"text-blue-600 dark:text-blue-400 font-bold":s?"text-red-600 dark:text-red-400":"text-gray-700 dark:text-gray-300"}">
                    <div class="text-xs font-medium">${e.toFormat("ccc")}</div>
                    <div class="text-lg ${n?"flex items-center justify-center w-8 h-8 mx-auto mt-1 rounded-full bg-blue-600 text-white":"mt-1"}">
                        ${e.day}
                    </div>
                    ${s?'<div class="text-[10px] text-red-600 dark:text-red-400 mt-1 font-medium">🚫 Blocked</div>':""}
                </div>
            </div>
        `}renderTimeSlot(e,t,n,s,a,r,o){return`
              <div class="grid grid-cols-8 border-b border-gray-200 dark:border-gray-700 last:border-b-0 min-h-[56px]"
                 data-time-slot="${e.time}">
                <!-- Time Label -->
                <div class="px-4 py-2 text-right border-r border-gray-200 dark:border-gray-700 text-sm text-gray-600 dark:text-gray-400">
                    ${e.display}
                </div>
                
                <!-- Day Columns -->
                ${n.map(l=>{const d=l.toISODate(),c=this.getAppointmentsForSlot(s[d]||[],e),h=ie(l,o),u=c.length>0;return`
                        <div class="flex flex-col px-1 py-1 border-r border-gray-200 dark:border-gray-700 last:border-r-0 ${h?"bg-red-50 dark:bg-red-900/10 opacity-50":u?"":"hover:bg-gray-50 dark:hover:bg-gray-700"} transition-colors ${u?"min-h-[60px]":""}"
                             data-date="${d}"
                             data-time="${e.time}">
                            ${c.map(f=>this.renderAppointmentBlock(f,a,e)).join("")}
                        </div>
                    `}).join("")}
            </div>
        `}renderAppointmentBlock(e,t,n){var u,f;const s=t.find(p=>p.id===e.providerId),a=W(),r=R(e.status,a),o=G(s),l=e.name||e.title||"Unknown",d=e.serviceName||"Appointment",c=((f=(u=this.scheduler)==null?void 0:u.settingsManager)==null?void 0:f.getTimeFormat())==="24h"?"HH:mm":"h:mm a",h=e.startDateTime.toFormat(c);return`
            <div class="appointment-block relative w-full p-2 rounded shadow-sm cursor-pointer hover:shadow-md transition-all text-xs border-l-4 mb-1"
                 style="background-color: ${r.bg}; border-left-color: ${r.border}; color: ${r.text};"
                 data-appointment-id="${e.id}"
                 title="${l} - ${d} at ${h} - ${e.status}">
                <div class="flex items-center gap-1.5 mb-1">
                    <span class="inline-block w-2 h-2 rounded-full flex-shrink-0" style="background-color: ${o};" title="${(s==null?void 0:s.name)||"Provider"}"></span>
                    <div class="font-semibold truncate">${h}</div>
                </div>
                <div class="truncate font-medium">${H(l)}</div>
                <div class="text-xs opacity-80 truncate">${H(d)}</div>
            </div>
        `}groupAppointmentsByDay(e,t){const n={};for(let s=0;s<7;s++){const a=t.plus({days:s}).toISODate();n[a]=[]}return e.forEach(s=>{const a=s.startDateTime.toISODate();n[a]&&n[a].push(s)}),n}getAppointmentsForSlot(e,t){const n=parseInt(t.time.split(":")[0],10),s=parseInt(t.time.split(":")[1],10);return e.filter(a=>{if(!a.startDateTime)return!1;const r=a.startDateTime.hour,o=a.startDateTime.minute;return r===n?s===0?o>=0&&o<30:o>=30&&o<60:!1})}attachEventListeners(e,t){e.querySelectorAll(".appointment-block").forEach(n=>{n.addEventListener("click",s=>{s.preventDefault(),s.stopPropagation();const a=parseInt(n.dataset.appointmentId,10),r=t.appointments.find(o=>o.id===a);r&&t.onAppointmentClick&&t.onAppointmentClick(r)})})}getContrastColor(e){const t=e.replace("#",""),n=parseInt(t.substr(0,2),16),s=parseInt(t.substr(2,2),16),a=parseInt(t.substr(4,2),16);return(.299*n+.587*s+.114*a)/255>.5?"#000000":"#FFFFFF"}renderWeeklyAppointmentsSection(e,t,n,s){var u,f;const a=document.getElementById("daily-provider-appointments");if(!a)return;const r=((f=(u=this.scheduler)==null?void 0:u.settingsManager)==null?void 0:f.getTimeFormat())==="24h"?"HH:mm":"h:mm a",o={};n.forEach(p=>{o[p.id]={},e.forEach(g=>{o[p.id][g.toISODate()]=[]})}),t.forEach(p=>{const g=p.startDateTime.toISODate();o[p.providerId]&&o[p.providerId][g]&&o[p.providerId][g].push(p)});const l={};n.forEach(p=>{l[p.id]=Object.values(o[p.id]).flat().length});const d=n.filter(p=>l[p.id]>0),c=e[0],h=e[e.length-1];a.innerHTML=`
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Weekly Schedule
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            ${c.toFormat("MMM d")} - ${h.toFormat("MMM d, yyyy")}
                        </p>
                    </div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        ${t.length} ${t.length===1?"appointment":"appointments"} this week
                    </span>
                </div>
            </div>
            
            ${d.length>0?`
                <!-- Provider Schedule Grid -->
                <div class="p-6">
                    <div class="space-y-6">
                        ${d.map(p=>{const g=p.color||"#3B82F6",v=Object.values(o[p.id]).flat();return`
                                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                                    <!-- Provider Header -->
                                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700"
                                         style="border-left: 4px solid ${g};">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold"
                                                 style="background-color: ${g};">
                                                ${p.name.charAt(0).toUpperCase()}
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-semibold text-gray-900 dark:text-white truncate">
                                                    ${this.escapeHtml(p.name)}
                                                </h4>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    ${v.length} ${v.length===1?"appointment":"appointments"} this week
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Days Grid -->
                                    <div class="grid grid-cols-1 md:grid-cols-7 divide-y md:divide-y-0 md:divide-x divide-gray-200 dark:divide-gray-700">
                                        ${e.map(S=>{const b=S.toISODate(),y=o[p.id][b]||[],I=S.hasSame(D.now(),"day");return`
                                                <div class="p-3 min-h-[120px] ${I?"bg-blue-50 dark:bg-blue-900/10":""}">
                                                    <!-- Day Header -->
                                                    <div class="mb-2">
                                                        <div class="text-xs font-medium ${I?"text-blue-600 dark:text-blue-400":"text-gray-500 dark:text-gray-400"}">
                                                            ${S.toFormat("ccc")}
                                                        </div>
                                                        <div class="${I?"inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-sm font-semibold":"text-lg font-semibold text-gray-900 dark:text-white"}">
                                                            ${S.day}
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Appointments List -->
                                                    <div class="space-y-1.5">
                                                        ${y.length>0?y.slice(0,3).map(C=>{const L=C.startDateTime.toFormat(r),P=C.name||C.customerName||C.title||"Unknown",_={confirmed:"bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200",pending:"bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200",completed:"bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200",cancelled:"bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200","no-show":"bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200"},J=_[C.status]||_.pending;return`
                                                                    <div class="text-xs p-2 rounded border border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500 cursor-pointer transition-colors"
                                                                         data-appointment-id="${C.id}">
                                                                        <div class="font-medium text-gray-900 dark:text-white truncate">${L}</div>
                                                                        <div class="text-gray-600 dark:text-gray-300 truncate">${this.escapeHtml(P)}</div>
                                                                        <span class="inline-block mt-1 px-1.5 py-0.5 text-[10px] font-medium rounded ${J}">
                                                                            ${C.status}
                                                                        </span>
                                                                    </div>
                                                                `}).join(""):'<div class="text-xs text-gray-400 dark:text-gray-500 italic">No appointments</div>'}
                                                        ${y.length>3?`
                                                            <div class="text-xs text-gray-500 dark:text-gray-400 font-medium text-center pt-1">
                                                                +${y.length-3} more
                                                            </div>
                                                        `:""}
                                                    </div>
                                                </div>
                                            `}).join("")}
                                    </div>
                                </div>
                            `}).join("")}
                    </div>
                </div>
            `:`
                <div class="p-12 text-center">
                    <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-5xl mb-3">event_available</span>
                    <p class="text-sm text-gray-600 dark:text-gray-400">No appointments scheduled this week</p>
                </div>
            `}
        `,this.attachWeeklySectionListeners(a,s)}attachWeeklySectionListeners(e,t){e.querySelectorAll("[data-appointment-id]").forEach(n=>{n.addEventListener("click",s=>{s.preventDefault(),s.stopPropagation();const a=parseInt(n.dataset.appointmentId,10),r=t.appointments.find(o=>o.id===a);r&&t.onAppointmentClick&&t.onAppointmentClick(r)})})}}class Xe{constructor(e){this.scheduler=e}render(e,t){var g,v,S;const{currentDate:n,appointments:s,providers:a,config:r}=t;console.log("[DayView] render() called with:",{currentDate:((g=n==null?void 0:n.toISO)==null?void 0:g.call(n))||n,appointmentsCount:(s==null?void 0:s.length)||0,providersCount:(a==null?void 0:a.length)||0,configKeys:r?Object.keys(r):[]}),s&&s.length>0&&console.log("[DayView] All appointments:",s.map(b=>{var y,I;return{id:b.id,start:((I=(y=b.startDateTime)==null?void 0:y.toISO)==null?void 0:I.call(y))||b.start,provider:b.providerId,name:b.name||b.title}}));const o=(r==null?void 0:r.slotMinTime)||"08:00",l=(r==null?void 0:r.slotMaxTime)||"17:00",d={startTime:o,endTime:l},c=((S=(v=this.scheduler)==null?void 0:v.settingsManager)==null?void 0:S.getTimeFormat())||"12h",h=be(d,c,30);ie(n,r==null?void 0:r.blockedPeriods)&&r.blockedPeriods.find(b=>{const y=n.toISODate();return y>=b.start&&y<=b.end});const f=n.toISODate(),p=s.filter(b=>b.startDateTime?b.startDateTime.toISODate()===f:(console.warn("[DayView] Appointment missing startDateTime:",b),!1)).sort((b,y)=>b.startDateTime.toMillis()-y.startDateTime.toMillis());console.log(`[DayView] Rendering ${f}: ${p.length} appointments found (from ${s.length} total)`),e.innerHTML=`
            <div class="scheduler-day-view bg-white dark:bg-gray-800">
                <!-- Calendar Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 p-6">
                    <!-- Time Slots Column (2/3 width) -->
                    <div class="lg:col-span-2 space-y-1">
                        ${h.map(b=>this.renderTimeSlot(b,p,a,t)).join("")}
                    </div>

                    <!-- Appointment List Sidebar (1/3 width) -->
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            Today's Schedule
                        </h3>
                        ${p.length>0?p.map(b=>this.renderAppointmentCard(b,a,t)).join(""):this.renderEmptyState()}
                    </div>
                </div>
            </div>
        `,this.attachEventListeners(e,t)}renderTimeSlot(e,t,n,s){const a=parseInt(e.time.split(":")[0],10),r=parseInt(e.time.split(":")[1],10),o=t.filter(l=>{if(!l.startDateTime)return!1;const d=l.startDateTime.hour,c=l.startDateTime.minute;return d===a?r===0?c>=0&&c<30:c>=30&&c<60:!1});return`
            <div class="time-slot flex items-start gap-4 p-2 rounded-lg border border-gray-200 dark:border-gray-700 
                        hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer min-h-[56px]"
                 data-time="${e.time}"
                 data-hour="${e.hour}">
                <!-- Time Label -->
                <div class="flex-shrink-0 w-20 text-right">
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">${e.display}</span>
                </div>

                <!-- Appointments Container -->
                <div class="flex-1 space-y-2">
                    ${o.length>0?o.map(l=>this.renderInlineAppointment(l,n)).join(""):'<div class="text-sm text-gray-400 dark:text-gray-500 italic">Available</div>'}
                </div>
            </div>
        `}renderInlineAppointment(e,t){var c,h;const n=t.find(u=>u.id===e.providerId),s=(n==null?void 0:n.color)||"#3B82F6",a=this.getContrastColor(s),r=((h=(c=this.scheduler)==null?void 0:c.settingsManager)==null?void 0:h.getTimeFormat())==="24h"?"HH:mm":"h:mm a",o=`${e.startDateTime.toFormat(r)} - ${e.endDateTime.toFormat(r)}`,l=e.name||e.title||"Unknown",d=e.serviceName||"Appointment";return`
            <div class="inline-appointment p-3 rounded-lg shadow-sm cursor-pointer hover:shadow-md transition-shadow"
                 style="background-color: ${s}; color: ${a};"
                 data-appointment-id="${e.id}">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-medium opacity-90 mb-1">${o}</div>
                        <div class="font-semibold truncate">${H(l)}</div>
                        <div class="text-sm opacity-90 truncate">${H(d)}</div>
                        ${n?`<div class="text-xs opacity-75 mt-1">with ${H(n.name)}</div>`:""}
                    </div>
                    <span class="material-symbols-outlined text-lg flex-shrink-0">arrow_forward</span>
                </div>
            </div>
        `}renderAppointmentCard(e,t,n){var f,p;const s=t.find(g=>g.id===e.providerId),a=W(),r=R(e.status,a),o=G(s),l=((p=(f=this.scheduler)==null?void 0:f.settingsManager)==null?void 0:p.getTimeFormat())==="24h"?"HH:mm":"h:mm a",d=`${e.startDateTime.toFormat(l)} - ${e.endDateTime.toFormat(l)}`,c=e.name||e.title||"Unknown",h=e.serviceName||"Appointment",u=Re(e.status);return`
            <div class="appointment-card p-4 rounded-lg border-2 hover:shadow-md transition-all cursor-pointer"
                 style="background-color: ${r.bg}; border-color: ${r.border}; color: ${r.text};"
                 data-appointment-id="${e.id}">
                <div class="flex items-start justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-3 h-3 rounded-full flex-shrink-0" style="background-color: ${o};" title="${(s==null?void 0:s.name)||"Provider"}"></span>
                        <div class="text-sm font-medium">${d}</div>
                    </div>
                    <span class="px-2 py-1 text-xs font-medium rounded-full border"
                          style="background-color: ${r.dot}; border-color: ${r.border}; color: white;">
                        ${u}
                    </span>
                </div>
                
                <h4 class="text-lg font-semibold mb-1">
                    ${H(c)}
                </h4>
                
                <p class="text-sm mb-2 opacity-90">
                    ${H(h)}
                </p>
                
                ${s?`
                    <div class="flex items-center gap-2 text-xs opacity-75">
                        <span class="material-symbols-outlined text-sm">person</span>
                        ${H(s.name)}
                    </div>
                `:""}
            </div>
        `}renderEmptyState(){return`
            <div class="text-center py-8">
                <span class="material-symbols-outlined text-gray-400 dark:text-gray-500 text-5xl mb-3">event_available</span>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">No appointments scheduled for this day</p>
                <p class="text-xs text-gray-500 dark:text-gray-500">Use the navigation arrows to view other dates</p>
            </div>
        `}attachEventListeners(e,t){e.querySelectorAll("[data-appointment-id]").forEach(n=>{n.addEventListener("click",s=>{s.preventDefault(),s.stopPropagation();const a=parseInt(n.dataset.appointmentId,10),r=t.appointments.find(o=>o.id===a);r&&t.onAppointmentClick&&t.onAppointmentClick(r)})})}getContrastColor(e){const t=e.replace("#",""),n=parseInt(t.substr(0,2),16),s=parseInt(t.substr(2,2),16),a=parseInt(t.substr(4,2),16);return(.299*n+.587*s+.114*a)/255>.5?"#000000":"#FFFFFF"}isDateBlocked(e,t){if(!t||t.length===0)return!1;const n=e.toISODate();return t.some(s=>{const a=s.start,r=s.end;return n>=a&&n<=r})}escapeHtml(e){const t=document.createElement("div");return t.textContent=e,t.innerHTML}}class Ke{constructor(e){this.scheduler=e,this.draggedAppointment=null,this.dragOverSlot=null,this.originalPosition=null}enableDragDrop(e){e.querySelectorAll(".appointment-block, .inline-appointment, .appointment-card").forEach(n=>{n.dataset.appointmentId&&(n.setAttribute("draggable","true"),n.classList.add("cursor-move"),n.addEventListener("dragstart",s=>{this.handleDragStart(s,n)}),n.addEventListener("dragend",s=>{this.handleDragEnd(s)}))}),e.querySelectorAll("[data-date], [data-date][data-time], .time-slot").forEach(n=>{n.addEventListener("dragover",s=>{s.preventDefault(),this.handleDragOver(s,n)}),n.addEventListener("dragleave",s=>{this.handleDragLeave(s,n)}),n.addEventListener("drop",s=>{s.preventDefault(),this.handleDrop(s,n)})})}handleDragStart(e,t){const n=parseInt(t.dataset.appointmentId);if(this.draggedAppointment=this.scheduler.appointments.find(s=>s.id===n),!this.draggedAppointment){e.preventDefault();return}this.originalPosition={date:this.draggedAppointment.startDateTime.toISODate(),time:this.draggedAppointment.startDateTime.toFormat("HH:mm")},e.dataTransfer.effectAllowed="move",e.dataTransfer.setData("text/plain",n),setTimeout(()=>{t.classList.add("opacity-50","scale-95")},0)}handleDragOver(e,t){this.draggedAppointment&&(e.dataTransfer.dropEffect="move",this.dragOverSlot=t,t.classList.add("ring-2","ring-blue-500","ring-inset","bg-blue-50","dark:bg-blue-900/20"))}handleDragLeave(e,t){e.relatedTarget&&t.contains(e.relatedTarget)||t.classList.remove("ring-2","ring-blue-500","ring-inset","bg-blue-50","dark:bg-blue-900/20")}async handleDrop(e,t){if(!this.draggedAppointment)return;const n=t.dataset.date,s=t.dataset.time||t.dataset.hour?`${t.dataset.hour}:00`:null;if(!n){console.error("❌ Drop target has no date"),this.resetDrag();return}let a;if(s)a=D.fromISO(`${n}T${s}`,{zone:this.scheduler.options.timezone});else{const c=this.draggedAppointment.startDateTime.toFormat("HH:mm");a=D.fromISO(`${n}T${c}`,{zone:this.scheduler.options.timezone})}const r=this.draggedAppointment.endDateTime.diff(this.draggedAppointment.startDateTime,"minutes").minutes,o=a.plus({minutes:r}),l=this.validateReschedule(a,o);if(!l.valid){this.showError(l.message),this.resetDrag();return}if(!await this.confirmReschedule(this.draggedAppointment,a,o)){this.resetDrag();return}await this.rescheduleAppointment(this.draggedAppointment.id,a,o),this.resetDrag()}handleDragEnd(e){e.target.classList.remove("opacity-50","scale-95"),document.querySelectorAll(".ring-blue-500").forEach(t=>{t.classList.remove("ring-2","ring-blue-500","ring-inset","bg-blue-50","dark:bg-blue-900/20")})}validateReschedule(e,t){const n=D.now().setZone(this.scheduler.options.timezone);if(e<n)return{valid:!1,message:"Cannot schedule appointments in the past"};const s=this.scheduler.calendarConfig;if(s!=null&&s.businessHours){const[r]=s.businessHours.startTime.split(":").map(Number),[o]=s.businessHours.endTime.split(":").map(Number);if(e.hour<r||t.hour>o)return{valid:!1,message:`Appointments must be within business hours (${s.businessHours.startTime} - ${s.businessHours.endTime})`}}return this.scheduler.appointments.filter(r=>{if(r.id===this.draggedAppointment.id||r.providerId!==this.draggedAppointment.providerId)return!1;const o=r.startDateTime,l=r.endDateTime;return e<l&&t>o}).length>0?{valid:!1,message:"This time slot conflicts with another appointment for this provider"}:{valid:!0}}async confirmReschedule(e,t,n){const s=e.customerName||"this customer",a=`${e.startDateTime.toFormat("EEE, MMM d")} at ${e.startDateTime.toFormat("h:mm a")}`,r=`${t.toFormat("EEE, MMM d")} at ${t.toFormat("h:mm a")}`;return confirm(`Reschedule appointment for ${s}?

From: ${a}
To: ${r}

This will update the appointment and notify the customer.`)}async rescheduleAppointment(e,t,n){try{this.showLoading();const s=await fetch(`/api/appointments/${e}`,{method:"PATCH",headers:{"Content-Type":"application/json","X-Requested-With":"XMLHttpRequest"},body:JSON.stringify({start:t.toISO(),end:n.toISO(),date:t.toISODate(),time:t.toFormat("HH:mm")})});if(!s.ok)throw new Error("Failed to reschedule appointment");const a=await s.json();if(await this.scheduler.loadAppointments(),this.scheduler.render(),this.showSuccess("Appointment rescheduled successfully"),typeof window<"u"){const r={source:"drag-drop",action:"reschedule",appointmentId:e};typeof window.emitAppointmentsUpdated=="function"?window.emitAppointmentsUpdated(r):window.dispatchEvent(new CustomEvent("appointments-updated",{detail:r}))}}catch(s){console.error("❌ Reschedule failed:",s),this.showError("Failed to reschedule appointment. Please try again."),await this.scheduler.loadAppointments(),this.scheduler.render()}finally{this.hideLoading()}}resetDrag(){this.draggedAppointment=null,this.dragOverSlot=null,this.originalPosition=null,document.querySelectorAll(".ring-blue-500").forEach(e=>{e.classList.remove("ring-2","ring-blue-500","ring-inset","bg-blue-50","dark:bg-blue-900/20")})}showLoading(){let e=document.getElementById("scheduler-loading");e?e.classList.remove("hidden"):(e=document.createElement("div"),e.id="scheduler-loading",e.className="fixed inset-0 bg-gray-900/50 dark:bg-gray-900/70 flex items-center justify-center z-50",e.innerHTML=`
                <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-xl">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
                    <p class="mt-4 text-gray-700 dark:text-gray-300">Rescheduling...</p>
                </div>
            `,document.body.appendChild(e))}hideLoading(){const e=document.getElementById("scheduler-loading");e&&e.classList.add("hidden")}showSuccess(e){this.showToast(e,"success")}showError(e){this.showToast(e,"error")}showToast(e,t="info"){const n=document.createElement("div"),s=t==="error"?"bg-red-600":t==="success"?"bg-green-600":"bg-blue-600";n.className=`fixed top-4 right-4 ${s} text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-slide-in`,n.textContent=e,document.body.appendChild(n),setTimeout(()=>{n.classList.add("animate-slide-out"),setTimeout(()=>n.remove(),300)},3e3)}}class Je{constructor(){this.settings={localization:null,booking:null,businessHours:null,providerSchedules:new Map},this.cache={lastFetch:null,ttl:5*60*1e3}}async init(){try{return await Promise.all([this.loadLocalizationSettings(),this.loadBookingSettings(),this.loadBusinessHours()]),this.cache.lastFetch=Date.now(),m.debug("⚙️ Settings Manager initialized",this.settings),!0}catch(e){return m.error("❌ Failed to initialize settings:",e),!1}}isCacheValid(){return this.cache.lastFetch?Date.now()-this.cache.lastFetch<this.cache.ttl:!1}async refresh(){return m.debug("🔄 Refreshing settings..."),this.cache.lastFetch=null,await this.init()}async loadLocalizationSettings(){try{const e=await fetch("/api/v1/settings/localization");if(!e.ok)throw new Error("Failed to load localization settings");const t=await e.json();return this.settings.localization=t.data||t,this.settings.localization.time_zone&&(window.appTimezone=this.settings.localization.time_zone),this.settings.localization}catch(e){m.error("Failed to load localization:",e);const t=Intl.DateTimeFormat().resolvedOptions().timeZone||"UTC";return this.settings.localization={timezone:t,time_zone:t,timeFormat:"12h",time_format:"12h",dateFormat:"MM/DD/YYYY",date_format:"MM/DD/YYYY",firstDayOfWeek:0,first_day_of_week:0},this.settings.localization}}getTimezone(){var e,t;return((e=this.settings.localization)==null?void 0:e.timezone)||((t=this.settings.localization)==null?void 0:t.time_zone)||Intl.DateTimeFormat().resolvedOptions().timeZone||"UTC"}getTimeFormat(){var e,t;return((e=this.settings.localization)==null?void 0:e.timeFormat)||((t=this.settings.localization)==null?void 0:t.time_format)||"12h"}getDateFormat(){var e;return((e=this.settings.localization)==null?void 0:e.date_format)||"MM/DD/YYYY"}getFirstDayOfWeek(){var n,s,a,r,o;const e=((n=this.settings.localization)==null?void 0:n.firstDayOfWeek)??((s=this.settings.localization)==null?void 0:s.first_day_of_week)??((r=(a=this.settings.localization)==null?void 0:a.context)==null?void 0:r.first_day_of_week);if(typeof e=="number")return m.debug(`📅 First day of week from settings: ${e} (${["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"][e]})`),e;const t=(o=this.settings.localization)==null?void 0:o.first_day;if(typeof t=="string"){const d={Sunday:0,sunday:0,Monday:1,monday:1,Tuesday:2,tuesday:2,Wednesday:3,wednesday:3,Thursday:4,thursday:4,Friday:5,friday:5,Saturday:6,saturday:6}[t]??0;return m.debug(`📅 First day of week from string "${t}": ${d}`),d}return m.debug("📅 First day of week using default: 0 (Sunday)"),0}formatTime(e){e instanceof D||(e=D.fromISO(e,{zone:this.getTimezone()}));const t=this.getTimeFormat()==="24h"?"HH:mm":"h:mm a";return e.toFormat(t)}formatDate(e){e instanceof D||(e=D.fromISO(e,{zone:this.getTimezone()}));const n=this.getDateFormat().replace("YYYY","yyyy").replace("DD","dd").replace("MM","MM");return e.toFormat(n)}formatDateTime(e){return`${this.formatDate(e)} ${this.formatTime(e)}`}getCurrency(){var e,t;return((t=(e=this.settings.localization)==null?void 0:e.context)==null?void 0:t.currency)||"ZAR"}getCurrencySymbol(){var e,t;return((t=(e=this.settings.localization)==null?void 0:e.context)==null?void 0:t.currency_symbol)||"R"}formatCurrency(e,t=2){const n=parseFloat(e)||0,s=this.getCurrencySymbol(),a=n.toFixed(t).replace(/\B(?=(\d{3})+(?!\d))/g,",");return`${s}${a}`}async loadBookingSettings(){try{const e=await fetch("/api/v1/settings/booking");if(!e.ok)throw new Error("Failed to load booking settings");const t=await e.json();return this.settings.booking=t.data||t,this.settings.booking}catch(e){return m.error("Failed to load booking settings:",e),this.settings.booking={enabled_fields:["first_name","last_name","email","phone","notes"],required_fields:["first_name","last_name","email"],min_booking_notice:1,max_booking_advance:30,allow_cancellation:!0,cancellation_deadline:24},this.settings.booking}}getEnabledFields(){var e;return((e=this.settings.booking)==null?void 0:e.enabled_fields)||[]}getRequiredFields(){var e;return((e=this.settings.booking)==null?void 0:e.required_fields)||[]}isFieldEnabled(e){return this.getEnabledFields().includes(e)}isFieldRequired(e){return this.getRequiredFields().includes(e)}getMinBookingNotice(){var e;return((e=this.settings.booking)==null?void 0:e.min_booking_notice)||1}getMaxBookingAdvance(){var e;return((e=this.settings.booking)==null?void 0:e.max_booking_advance)||30}getEarliestBookableTime(){const e=this.getMinBookingNotice();return D.now().setZone(this.getTimezone()).plus({hours:e})}getLatestBookableTime(){const e=this.getMaxBookingAdvance();return D.now().setZone(this.getTimezone()).plus({days:e})}isWithinBookingWindow(e){e instanceof D||(e=D.fromISO(e,{zone:this.getTimezone()}));const t=this.getEarliestBookableTime(),n=this.getLatestBookableTime();return e>=t&&e<=n}async loadBusinessHours(){try{const e=await fetch("/api/v1/settings/business-hours");if(!e.ok)throw new Error("Failed to load business hours");const t=await e.json();return this.settings.businessHours=t.data||t,this.settings.businessHours}catch(e){return m.error("Failed to load business hours:",e),this.settings.businessHours={enabled:!0,schedule:{monday:{enabled:!0,start:"09:00",end:"17:00"},tuesday:{enabled:!0,start:"09:00",end:"17:00"},wednesday:{enabled:!0,start:"09:00",end:"17:00"},thursday:{enabled:!0,start:"09:00",end:"17:00"},friday:{enabled:!0,start:"09:00",end:"17:00"},saturday:{enabled:!1,start:"09:00",end:"17:00"},sunday:{enabled:!1,start:"09:00",end:"17:00"}},breaks:[]},this.settings.businessHours}}getBusinessHours(){return this.settings.businessHours}getBusinessHoursForDay(e){var s,a;const n=["sunday","monday","tuesday","wednesday","thursday","friday","saturday"][e];return((a=(s=this.settings.businessHours)==null?void 0:s.schedule)==null?void 0:a[n])||{enabled:!1,start:"09:00",end:"17:00"}}isWorkingDay(e){return this.getBusinessHoursForDay(e).enabled}isWithinBusinessHours(e){e instanceof D||(e=D.fromISO(e,{zone:this.getTimezone()}));const t=this.getBusinessHoursForDay(e.weekday%7);if(!t.enabled)return!1;const[n,s]=t.start.split(":").map(Number),[a,r]=t.end.split(":").map(Number),o=e.set({hour:n,minute:s,second:0}),l=e.set({hour:a,minute:r,second:0});return e>=o&&e<=l}getBusinessHoursRange(){var a;const e=((a=this.settings.businessHours)==null?void 0:a.schedule)||{},t=Object.values(e).filter(r=>r.enabled);if(t.length===0)return{start:"09:00",end:"17:00"};const n=t.map(r=>r.start),s=t.map(r=>r.end);return{start:n.sort()[0],end:s.sort().reverse()[0]}}async loadProviderSchedule(e){try{const t=await fetch(`/api/providers/${e}/schedule`);if(!t.ok)throw new Error("Failed to load provider schedule");const n=await t.json(),s=n.data||n;return this.settings.providerSchedules.set(e,s),s}catch(t){return m.error(`Failed to load schedule for provider ${e}:`,t),null}}getProviderSchedule(e){return this.settings.providerSchedules.get(e)}async isProviderAvailable(e,t){t instanceof D||(t=D.fromISO(t,{zone:this.getTimezone()})),this.settings.providerSchedules.has(e)||await this.loadProviderSchedule(e);const n=this.getProviderSchedule(e);if(!n)return!0;const a=["sunday","monday","tuesday","wednesday","thursday","friday","saturday"][t.weekday%7],r=n[a];if(!r||!r.enabled)return!1;const[o,l]=r.start.split(":").map(Number),[d,c]=r.end.split(":").map(Number),h=t.set({hour:o,minute:l,second:0}),u=t.set({hour:d,minute:c,second:0});return t>=h&&t<=u}async getAvailableSlots(e,t,n=60){const s=typeof t=="string"?D.fromISO(t,{zone:this.getTimezone()}):t,a=await this.getProviderSchedule(e)||this.getBusinessHoursForDay(s.weekday%7);if(!a.enabled)return[];const[r,o]=a.start.split(":").map(Number),[l,d]=a.end.split(":").map(Number),c=[];let h=s.set({hour:r,minute:o,second:0});const u=s.set({hour:l,minute:d,second:0});for(;h.plus({minutes:n})<=u;)c.push({start:h,end:h.plus({minutes:n}),available:!0}),h=h.plus({minutes:30});return c}}class Qe{constructor(e){this.scheduler=e,this.modal=null,this.currentAppointment=null,this.init()}init(){this.createModal(),this.attachEventListeners()}createModal(){document.body.insertAdjacentHTML("beforeend",`
            <div id="appointment-details-modal" class="scheduler-modal hidden" role="dialog" aria-labelledby="appointment-modal-title" aria-modal="true">
                <div class="scheduler-modal-backdrop" data-modal-close></div>
                <div class="scheduler-modal-dialog">
                    <div class="scheduler-modal-panel">
                    <!-- Header -->
                    <div class="scheduler-modal-header">
                        <div class="flex items-center gap-3">
                            <div id="appointment-status-indicator" class="w-3 h-3 rounded-full"></div>
                            <h3 id="appointment-modal-title" class="text-xl font-semibold text-gray-900 dark:text-white">
                                Appointment Details
                            </h3>
                        </div>
                        <button type="button" data-modal-close class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                            <span class="material-symbols-outlined text-2xl">close</span>
                        </button>
                    </div>
                    
                    <!-- Body -->
                    <div class="scheduler-modal-body">
                        <!-- Loading State -->
                        <div id="details-loading" class="hidden text-center py-8">
                            <div class="loading-spinner mx-auto mb-4"></div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Loading details...</p>
                        </div>
                        
                        <!-- Content -->
                        <div id="details-content" class="space-y-4">
                            <!-- Date & Time Section -->
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                                <div class="flex items-start gap-3">
                                    <span class="material-symbols-outlined text-2xl text-blue-600 dark:text-blue-400">event</span>
                                    <div class="flex-1">
                                        <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Date & Time</h4>
                                        <p id="detail-date" class="text-base font-semibold text-gray-900 dark:text-white mb-1"></p>
                                        <p id="detail-time" class="text-sm text-gray-700 dark:text-gray-300"></p>
                                        <p id="detail-duration" class="text-xs text-gray-600 dark:text-gray-400 mt-1"></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Customer Section -->
                            <div>
                                <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm">person</span>
                                    Customer Information
                                </h4>
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-sm text-gray-400">badge</span>
                                        <span id="detail-customer-name" class="text-sm text-gray-900 dark:text-white font-medium"></span>
                                    </div>
                                    <div id="detail-customer-email-wrapper" class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-sm text-gray-400">mail</span>
                                        <a id="detail-customer-email" href="#" class="text-sm text-blue-600 dark:text-blue-400 hover:underline"></a>
                                    </div>
                                    <div id="detail-customer-phone-wrapper" class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-sm text-gray-400">phone</span>
                                        <a id="detail-customer-phone" href="#" class="text-sm text-gray-900 dark:text-white hover:underline"></a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Service & Provider in Grid -->
                            <div class="grid grid-cols-2 gap-4">
                                <!-- Service Section -->
                                <div>
                                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-sm">medical_services</span>
                                        Service
                                    </h4>
                                    <p id="detail-service-name" class="text-sm text-gray-900 dark:text-white font-medium mb-1"></p>
                                    <p id="detail-service-price" class="text-base font-bold text-green-600 dark:text-green-400"></p>
                                </div>
                                
                                <!-- Provider Section -->
                                <div>
                                    <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-sm">person_pin</span>
                                        Provider
                                    </h4>
                                    <div class="flex items-center gap-2">
                                        <div id="detail-provider-color" class="w-8 h-8 rounded-full flex-shrink-0"></div>
                                        <span id="detail-provider-name" class="text-sm text-gray-900 dark:text-white font-medium"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Notes Section -->
                            <div id="detail-notes-wrapper" class="hidden">
                                <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm">note</span>
                                    Notes
                                </h4>
                                <p id="detail-notes" class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap bg-gray-50 dark:bg-gray-700 rounded-lg p-2"></p>
                            </div>
                            
                            <!-- Status Management -->
                            <div class="flex items-center justify-between pt-3 border-t border-gray-200 dark:border-gray-700">
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Status</span>
                                <div class="flex items-center gap-2">
                                    <div class="relative">
                                        <select id="detail-status-select" class="appearance-none text-xs font-medium rounded-full pl-3 pr-8 py-1.5 border-0 focus:ring-2 focus:ring-blue-500 cursor-pointer">
                                            <option value="pending">Pending</option>
                                            <option value="confirmed">Confirmed</option>
                                            <option value="completed">Completed</option>
                                            <option value="cancelled">Cancelled</option>
                                            <option value="no-show">No Show</option>
                                        </select>
                                        <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-sm pointer-events-none">expand_more</span>
                                    </div>
                                    <button type="button" id="btn-save-status" class="hidden px-3 py-1.5 text-xs font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors shadow-sm">
                                        Save
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer Actions -->
                    <div class="scheduler-modal-footer">
                        <div class="flex gap-2">
                            <button type="button" data-modal-close class="btn btn-secondary">
                                Close
                            </button>
                            <button type="button" id="btn-cancel-appointment" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-red-700 bg-white border border-red-300 rounded-lg hover:bg-red-50 hover:border-red-400 dark:bg-gray-800 dark:text-red-400 dark:border-red-800 dark:hover:bg-red-900/20 transition-colors">
                                <span class="material-symbols-outlined text-base">event_busy</span>
                                Cancel Appointment
                            </button>
                        </div>
                        <button type="button" id="btn-edit-appointment" class="btn btn-primary">
                            <span class="material-symbols-outlined text-base">edit</span>
                            Edit Appointment
                        </button>
                    </div>
                </div>
            </div>
        </div>
        `),this.modal=document.getElementById("appointment-details-modal")}attachEventListeners(){this.modal.querySelectorAll("[data-modal-close]").forEach(a=>{a.addEventListener("click",()=>this.close())}),document.addEventListener("keydown",a=>{a.key==="Escape"&&this.modal.classList.contains("scheduler-modal-open")&&this.close()}),this.modal.querySelector("#btn-edit-appointment").addEventListener("click",()=>{this.currentAppointment&&this.handleEdit(this.currentAppointment)}),this.modal.querySelector("#btn-cancel-appointment").addEventListener("click",()=>{this.currentAppointment&&this.handleCancel(this.currentAppointment)});const n=this.modal.querySelector("#detail-status-select"),s=this.modal.querySelector("#btn-save-status");n.addEventListener("change",()=>{this.currentAppointment&&n.value!==this.currentAppointment.status?(s.classList.remove("hidden"),this.updateStatusSelectStyling(n,n.value)):(s.classList.add("hidden"),this.currentAppointment&&this.updateStatusSelectStyling(n,this.currentAppointment.status))}),s.addEventListener("click",async()=>{this.currentAppointment&&await this.handleStatusChange(this.currentAppointment,n.value)})}open(e){if(!this.modal){console.error("[AppointmentDetailsModal] Modal element not found!");return}try{this.currentAppointment=e,this.populateDetails(e),this.modal.classList.remove("hidden"),document.body.style.overflow="hidden",requestAnimationFrame(()=>{this.modal.classList.add("scheduler-modal-open")})}catch(t){console.error("[AppointmentDetailsModal] Error opening modal:",t)}}close(){this.modal.classList.remove("scheduler-modal-open"),document.body.style.overflow="",setTimeout(()=>{this.modal.classList.add("hidden"),this.currentAppointment=null},300)}populateDetails(e){var t;try{const n=e.startDateTime||D.fromISO(e.start_time),s=e.endDateTime||D.fromISO(e.end_time),a=((t=this.scheduler.settingsManager)==null?void 0:t.getTimeFormat())==="24h"?"HH:mm":"h:mm a",r={confirmed:{bg:"bg-green-100 dark:bg-green-900",text:"text-green-800 dark:text-green-200",indicator:"bg-green-500"},pending:{bg:"bg-amber-100 dark:bg-amber-900",text:"text-amber-800 dark:text-amber-200",indicator:"bg-amber-500"},completed:{bg:"bg-blue-100 dark:bg-blue-900",text:"text-blue-800 dark:text-blue-200",indicator:"bg-blue-500"},cancelled:{bg:"bg-red-100 dark:bg-red-900",text:"text-red-800 dark:text-red-200",indicator:"bg-red-500"},booked:{bg:"bg-purple-100 dark:bg-purple-900",text:"text-purple-800 dark:text-purple-200",indicator:"bg-purple-500"}},o=r[e.status]||r.pending,l=this.modal.querySelector("#appointment-status-indicator");l.className=`w-3 h-3 rounded-full ${o.indicator}`,this.modal.querySelector("#detail-date").textContent=n.toFormat("EEEE, MMMM d, yyyy"),this.modal.querySelector("#detail-time").textContent=`${n.toFormat(a)} - ${s.toFormat(a)}`;const d=e.serviceDuration||Math.round(s.diff(n,"minutes").minutes);this.modal.querySelector("#detail-duration").textContent=`Duration: ${d} minutes`,this.modal.querySelector("#detail-customer-name").textContent=e.name||e.customerName||"Unknown",e.email?(this.modal.querySelector("#detail-customer-email").textContent=e.email,this.modal.querySelector("#detail-customer-email").href=`mailto:${e.email}`,this.modal.querySelector("#detail-customer-email-wrapper").classList.remove("hidden")):this.modal.querySelector("#detail-customer-email-wrapper").classList.add("hidden"),e.phone?(this.modal.querySelector("#detail-customer-phone").textContent=e.phone,this.modal.querySelector("#detail-customer-phone").href=`tel:${e.phone}`,this.modal.querySelector("#detail-customer-phone-wrapper").classList.remove("hidden")):this.modal.querySelector("#detail-customer-phone-wrapper").classList.add("hidden"),this.modal.querySelector("#detail-service-name").textContent=e.serviceName||"Service",e.servicePrice?this.modal.querySelector("#detail-service-price").textContent=`$${parseFloat(e.servicePrice).toFixed(2)}`:this.modal.querySelector("#detail-service-price").textContent="";const c=e.providerColor||"#3B82F6";this.modal.querySelector("#detail-provider-color").style.backgroundColor=c,this.modal.querySelector("#detail-provider-name").textContent=e.providerName||"Provider",e.notes&&e.notes.trim()?(this.modal.querySelector("#detail-notes").textContent=e.notes,this.modal.querySelector("#detail-notes-wrapper").classList.remove("hidden")):this.modal.querySelector("#detail-notes-wrapper").classList.add("hidden");const h=this.modal.querySelector("#detail-status-select");h.value=e.status,this.updateStatusSelectStyling(h,e.status),this.modal.querySelector("#btn-save-status").classList.add("hidden");const u=this.modal.querySelector("#btn-cancel-appointment");e.status==="cancelled"||e.status==="completed"?u.classList.add("hidden"):u.classList.remove("hidden")}catch(n){throw console.error("[AppointmentDetailsModal] Error populating details:",n),n}}updateStatusSelectStyling(e,t){const n={confirmed:"bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200",pending:"bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200",completed:"bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200",cancelled:"bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200","no-show":"bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200"},s=n[t]||n.pending;e.className=`appearance-none text-xs font-medium rounded-full pl-3 pr-8 py-1.5 border-0 focus:ring-2 focus:ring-blue-500 cursor-pointer ${s}`}async handleStatusChange(e,t){var r;const n=this.modal.querySelector("#btn-save-status"),s=this.modal.querySelector("#detail-status-select"),a=e.status;n.disabled=!0,n.textContent="Saving...";try{const o=await fetch(`/api/appointments/${e.id}/status`,{method:"PATCH",headers:{"Content-Type":"application/json"},body:JSON.stringify({status:t})});if(!o.ok){const l=await o.json();throw new Error(((r=l.error)==null?void 0:r.message)||"Failed to update status")}if(e.status=t,this.currentAppointment.status=t,this.scheduler.dragDropManager&&this.scheduler.dragDropManager.showToast("Status updated successfully","success"),n.classList.add("hidden"),n.disabled=!1,n.textContent="Save",await this.scheduler.loadAppointments(),this.scheduler.render(),typeof window<"u"){const l={source:"status-change",appointmentId:e.id,status:t};typeof window.emitAppointmentsUpdated=="function"?window.emitAppointmentsUpdated(l):window.dispatchEvent(new CustomEvent("appointments-updated",{detail:l}))}}catch(o){console.error("Error updating status:",o),s.value=a,this.updateStatusSelectStyling(s,a),n.classList.add("hidden"),n.disabled=!1,n.textContent="Save",this.scheduler.dragDropManager?this.scheduler.dragDropManager.showToast(o.message||"Failed to update status","error"):alert(o.message||"Failed to update status. Please try again.")}}handleEdit(e){this.close();const t=e.hash||e.id;window.location.href=`/appointments/edit/${t}`}async handleCancel(e){const t=e.startDateTime||D.fromISO(e.start_time);if(confirm(`Are you sure you want to cancel this appointment?

Customer: ${e.name||e.customerName||"Unknown"}
Date: ${t.toFormat("MMMM d, yyyy h:mm a")}`))try{if(!(await fetch(`/api/appointments/${e.id}/status`,{method:"PATCH",headers:{"Content-Type":"application/json"},body:JSON.stringify({status:"cancelled"})})).ok)throw new Error("Failed to cancel appointment");if(this.scheduler.dragDropManager&&this.scheduler.dragDropManager.showToast("Appointment cancelled successfully","success"),this.close(),await this.scheduler.loadAppointments(),this.scheduler.render(),typeof window<"u"){const a={source:"status-change",appointmentId:e.id,status:"cancelled"};typeof window.emitAppointmentsUpdated=="function"?window.emitAppointmentsUpdated(a):window.dispatchEvent(new CustomEvent("appointments-updated",{detail:a}))}}catch(s){console.error("Error cancelling appointment:",s),this.scheduler.dragDropManager?this.scheduler.dragDropManager.showToast("Failed to cancel appointment","error"):alert("Failed to cancel appointment. Please try again.")}}}class et{constructor(e,t={}){if(this.containerId=e,this.container=document.getElementById(e),!this.container)throw new Error(`Container with ID "${e}" not found`);this.currentDate=D.now(),this.currentView="month",this.appointments=[],this.providers=[],this.visibleProviders=new Set,this.statusFilter=t.statusFilter??null,this.renderDebounceTimer=null,this.renderDebounceDelay=100,this.settingsManager=new Je,this.views={month:new We(this),week:new Ze(this),day:new Xe(this)},this.dragDropManager=new Ke(this),this.appointmentDetailsModal=new Qe(this),this.options=t}async init(){try{m.info("🚀 Initializing Custom Scheduler..."),m.debug("⚙️  Loading settings..."),await this.settingsManager.init(),m.debug("✅ Settings loaded"),this.options.timezone=this.settingsManager.getTimezone(),this.currentDate=this.currentDate.setZone(this.options.timezone),m.debug(`🌍 Timezone: ${this.options.timezone}`),m.debug("📊 Loading data..."),await Promise.all([this.loadCalendarConfig(),this.loadProviders(),this.loadAppointments()]),m.debug("✅ Data loaded"),m.debug("📋 Raw providers data:",this.providers),this.providers.forEach(e=>{const t=typeof e.id=="string"?parseInt(e.id,10):e.id;this.visibleProviders.add(t),m.debug(`   ✓ Adding provider ${e.name} (ID: ${t}) to visible set`)}),m.debug("✅ Visible providers initialized:",Array.from(this.visibleProviders)),m.debug("📊 Appointments provider IDs:",this.appointments.map(e=>`${e.id}: provider ${e.providerId}`)),m.info("🔍 P0-2 DIAGNOSTIC CHECK:"),m.info("   Visible providers Set:",this.visibleProviders),m.info("   Visible providers Array:",Array.from(this.visibleProviders)),this.appointments.forEach(e=>{const t=this.visibleProviders.has(e.providerId);m.info(`   Appointment ${e.id}: providerId=${e.providerId} (${typeof e.providerId}), has match=${t}`)}),this.toggleDailyAppointmentsSection(),m.debug("🎨 Rendering view..."),this.render(),m.info("✅ Custom Scheduler initialized successfully"),m.debug("📋 Summary:"),m.debug(`   - Providers: ${this.providers.length}`),m.debug(`   - Appointments: ${this.appointments.length}`),m.debug(`   - View: ${this.currentView}`),m.debug(`   - Timezone: ${this.options.timezone}`),this.appointments.length===0&&(m.info("💡 To see appointments, implement these backend endpoints:"),m.info("   1. GET /api/appointments?start=YYYY-MM-DD&end=YYYY-MM-DD"),m.info("   2. GET /api/providers?includeColors=true"),m.info("   3. GET /api/v1/settings/* (optional, has fallbacks)"))}catch(e){m.error("❌ Failed to initialize scheduler:",e),m.error("Error stack:",e.stack),this.renderError(`Failed to load scheduler: ${e.message}`)}}async loadCalendarConfig(){try{const e=await fetch("/api/v1/settings/calendarConfig");if(!e.ok)throw new Error("Failed to load calendar config");const t=await e.json();this.calendarConfig=t.data||t,m.debug("📅 Calendar config loaded:",this.calendarConfig)}catch(e){m.error("Failed to load calendar config:",e),this.calendarConfig={timeFormat:"12h",firstDayOfWeek:0,businessHours:{startTime:"09:00",endTime:"17:00"}}}}async loadProviders(){try{const e=await fetch("/api/providers?includeColors=true");if(!e.ok)throw new Error("Failed to load providers");const t=await e.json();this.providers=t.data||t||[],m.debug("👥 Providers loaded:",this.providers.length)}catch(e){m.error("Failed to load providers:",e),this.providers=[]}}async loadAppointments(e=null,t=null){try{if(!e||!t){const o=this.getDateRangeForView();e=o.start,t=o.end}const n=new URLSearchParams({start:e,end:t});if(this.statusFilter&&n.append("status",this.statusFilter),this.options.futureOnly!==!1){n.append("futureOnly","1");const o=this.options.lookAheadDays??90;n.append("lookAheadDays",o.toString())}const s=`${this.options.apiBaseUrl}?${n.toString()}`;m.debug("🔄 Loading appointments from:",s);const a=await fetch(s);if(!a.ok)throw new Error("Failed to load appointments");const r=await a.json();return m.debug("📥 Raw API response:",r),this.appointments=r.data||r||[],m.debug("📦 Extracted appointments array:",this.appointments),this.appointments=this.appointments.map(o=>{const l=o.id??o.appointment_id??o.appointmentId,d=o.providerId??o.provider_id,c=o.serviceId??o.service_id,h=o.customerId??o.customer_id,u=o.start??o.start_time??o.startTime,f=o.end??o.end_time??o.endTime;(!u||!f)&&m.warn("Appointment missing start/end fields:",o),d==null&&m.error("❌ Appointment missing providerId:",o);const p=u?D.fromISO(u,{zone:this.options.timezone}):null,g=f?D.fromISO(f,{zone:this.options.timezone}):null;return{...o,id:l!=null?parseInt(l,10):void 0,providerId:d!=null?parseInt(d,10):void 0,serviceId:c!=null?parseInt(c,10):void 0,customerId:h!=null?parseInt(h,10):void 0,startDateTime:p,endDateTime:g}}),m.debug("📅 Appointments loaded:",this.appointments.length),m.debug("📋 Appointment details:",this.appointments),this.appointments}catch(n){return m.error("❌ Failed to load appointments:",n),this.appointments=[],[]}}getDateRangeForView(){let e,t;switch(this.currentView){case"day":e=this.currentDate.startOf("day"),t=this.currentDate.endOf("day");break;case"week":e=this.currentDate.startOf("week"),t=this.currentDate.endOf("week");break;case"month":default:const n=this.currentDate.startOf("month"),s=this.currentDate.endOf("month");e=n.startOf("week"),t=s.endOf("week");break}return{start:e.toISODate(),end:t.toISODate()}}getFilteredAppointments(){const e=this.appointments.filter(t=>{const n=typeof t.providerId=="string"?parseInt(t.providerId,10):t.providerId;return this.visibleProviders.has(n)});return e.length===0&&this.appointments.length>0&&m.warn("No appointments visible - all filtered out by provider visibility"),e}toggleProvider(e){this.visibleProviders.has(e)?this.visibleProviders.delete(e):this.visibleProviders.add(e),this.render()}async setStatusFilter(e){const t=typeof e=="string"&&e!==""?e:null;this.statusFilter!==t&&(this.statusFilter=t,this.container&&(this.container.dataset.activeStatus=t||""),await this.loadAppointments(),this.render())}async changeView(e){if(!["day","week","month"].includes(e)){console.error("Invalid view:",e);return}this.currentView=e,this.toggleDailyAppointmentsSection(),await this.loadAppointments(),this.render()}async navigateToToday(){this.currentDate=D.now().setZone(this.options.timezone),await this.loadAppointments(),this.render()}async navigateNext(){switch(this.currentView){case"day":this.currentDate=this.currentDate.plus({days:1});break;case"week":this.currentDate=this.currentDate.plus({weeks:1});break;case"month":this.currentDate=this.currentDate.plus({months:1});break}await this.loadAppointments(),this.render()}async navigatePrev(){switch(this.currentView){case"day":this.currentDate=this.currentDate.minus({days:1});break;case"week":this.currentDate=this.currentDate.minus({weeks:1});break;case"month":this.currentDate=this.currentDate.minus({months:1});break}await this.loadAppointments(),this.render()}render(){this.renderDebounceTimer&&clearTimeout(this.renderDebounceTimer),this.renderDebounceTimer=setTimeout(()=>{this._performRender()},this.renderDebounceDelay)}_performRender(){if((!this.container||!document.body.contains(this.container))&&(this.container=document.getElementById(this.containerId),!this.container)){m.error(`Container #${this.containerId} not found in DOM`);return}const e=this.getFilteredAppointments();m.debug("🎨 Rendering view:",this.currentView),m.debug("🔍 Filtered appointments for display:",e.length),m.debug("👥 Visible providers:",Array.from(this.visibleProviders)),m.debug("📋 All appointments:",this.appointments.length),this.updateDateDisplay();const t=this.views[this.currentView];t&&typeof t.render=="function"?(t.render(this.container,{currentDate:this.currentDate,appointments:e,providers:this.providers,config:this.calendarConfig,settings:this.settingsManager,onAppointmentClick:this.handleAppointmentClick.bind(this)}),this.dragDropManager&&this.dragDropManager.enableDragDrop(this.container)):(m.error(`View not implemented: ${this.currentView}`),this.container.innerHTML=`
                <div class="flex items-center justify-center p-12">
                    <div class="text-center">
                        <span class="material-symbols-outlined text-gray-400 text-6xl mb-4">construction</span>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                            ${this.currentView.charAt(0).toUpperCase()+this.currentView.slice(1)} View Coming Soon
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            This view is currently under development.
                        </p>
                    </div>
                </div>
            `)}handleAppointmentClick(e){m.debug("[SchedulerCore] handleAppointmentClick called with:",e),m.debug("[SchedulerCore] appointmentDetailsModal exists:",!!this.appointmentDetailsModal),this.options.onAppointmentClick?(m.debug("[SchedulerCore] Using custom onAppointmentClick"),this.options.onAppointmentClick(e)):(m.debug("[SchedulerCore] Opening modal with appointmentDetailsModal.open()"),this.appointmentDetailsModal.open(e))}renderError(e){(!this.container||!document.body.contains(this.container))&&(this.container=document.getElementById(this.containerId)),this.container&&(this.container.innerHTML=`
            <div class="flex items-center justify-center p-12">
                <div class="text-center">
                    <span class="material-symbols-outlined text-red-500 text-6xl mb-4">error</span>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Error</h3>
                    <p class="text-gray-600 dark:text-gray-400">${e}</p>
                </div>
            </div>
        `)}destroy(){this.renderDebounceTimer&&(clearTimeout(this.renderDebounceTimer),this.renderDebounceTimer=null),this.container=null,this.appointments=[],this.providers=[],this.visibleProviders.clear()}toggleDailyAppointmentsSection(){const e=document.getElementById("daily-provider-appointments");e&&(this.currentView==="day"?e.style.display="none":e.style.display="block")}updateDateDisplay(){const e=document.getElementById("scheduler-date-display");if(!e)return;let t="";switch(this.currentView){case"day":t=this.currentDate.toFormat("EEEE, MMMM d, yyyy");break;case"week":const n=this.currentDate.startOf("week"),s=n.plus({days:6});n.month===s.month?t=`${n.toFormat("MMM d")} - ${s.toFormat("d, yyyy")}`:n.year===s.year?t=`${n.toFormat("MMM d")} - ${s.toFormat("MMM d, yyyy")}`:t=`${n.toFormat("MMM d, yyyy")} - ${s.toFormat("MMM d, yyyy")}`;break;case"month":default:t=this.currentDate.toFormat("MMMM yyyy");break}e.textContent=t}}function tt(i){const{providerSelectId:e,serviceSelectId:t,dateInputId:n,timeInputId:s,gridId:a="time-slots-grid",loadingId:r="time-slots-loading",emptyId:o="time-slots-empty",errorId:l="time-slots-error",errorMsgId:d="time-slots-error-message",promptId:c="time-slots-prompt",excludeAppointmentId:h,preselectServiceId:u,initialTime:f,onTimeSelected:p}=i||{},g=document.getElementById(e),v=document.getElementById(t),S=document.getElementById(n),b=document.getElementById(s);if(!g||!v||!S||!b){console.warn("[time-slots-ui] Missing required elements");return}const y={grid:document.getElementById(a),loading:document.getElementById(r),empty:document.getElementById(o),error:document.getElementById(l),errorMsg:document.getElementById(d),prompt:document.getElementById(c),availableDatesHint:document.getElementById("available-dates-hint"),availableDatesPills:document.getElementById("available-dates-pills"),noAvailabilityWarning:document.getElementById("no-availability-warning")},I=60*1e3,C=new Map;let L=0;function P(k){const w=C.get(k);return w?Date.now()-w.fetchedAt>I?(C.delete(k),null):w:null}async function _(k,w,A,x=!1){var q;const E=Ae(k,w,A,h),T=x?null:P(E);if(T)return T.data;const $=++L,F=new URLSearchParams({provider_id:k,service_id:w,days:"60"});A&&F.append("start_date",A),h&&F.append("exclude_appointment_id",String(h));let O;try{O=await fetch(`/api/availability/calendar?${F.toString()}`,{headers:{Accept:"application/json","X-Requested-With":"XMLHttpRequest"}})}catch{throw new Error("Unable to reach availability service. Check your connection.")}const M=await O.json().catch(()=>({}));if(!O.ok){const U=((q=M==null?void 0:M.error)==null?void 0:q.message)||(M==null?void 0:M.error)||"Failed to load availability calendar";throw new Error(U)}const N=Te((M==null?void 0:M.data)??M??{});return $===L&&C.set(E,{data:N,fetchedAt:Date.now()}),N}function J(k,w){const{date:A,autoSelected:x}=Ce(k,w);return x&&A&&(S.value=A),{date:A,updated:x}}function ke(k){var E,T;if(!y.availableDatesHint||!y.availableDatesPills)return;if((E=y.noAvailabilityWarning)==null||E.classList.add("hidden"),!k||k.length===0){y.availableDatesHint.classList.add("hidden"),(T=y.noAvailabilityWarning)==null||T.classList.remove("hidden");return}const w=5,A=k.slice(0,w),x=k.length-w;if(y.availableDatesPills.innerHTML="",A.forEach($=>{const F=document.createElement("button");F.type="button",F.className="px-2 py-1 text-xs bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded hover:bg-green-200 dark:hover:bg-green-900/50 transition-colors",F.textContent=$e($),F.dataset.date=$,F.addEventListener("click",()=>{S.value=$,b.value="",z()}),y.availableDatesPills.appendChild(F)}),x>0){const $=document.createElement("span");$.className="px-2 py-1 text-xs text-gray-500 dark:text-gray-400",$.textContent=`+${x} more`,y.availableDatesPills.appendChild($)}y.availableDatesHint.classList.remove("hidden")}async function re(k){if(v.innerHTML='<option value="">Loading services...</option>',v.disabled=!0,!k){v.innerHTML='<option value="">Select a provider first...</option>',v.disabled=!1;return}try{const w=await fetch(`/api/v1/providers/${k}/services`);if(!w.ok)throw console.error("[time-slots-ui] Service API error:",w.status),new Error("Failed to load services");const x=(await w.json()).data||[];if(x.length===0){v.innerHTML='<option value="">No services available for this provider</option>',v.disabled=!1;return}v.innerHTML='<option value="">Select a service...</option>';let E=!1;return x.forEach(T=>{const $=document.createElement("option");$.value=T.id,$.textContent=`${T.name} - $${parseFloat(T.price).toFixed(2)}`,$.dataset.duration=T.durationMin||T.duration_min,$.dataset.price=T.price,u&&String(u)===String(T.id)&&($.selected=!0,E=!0),v.appendChild($)}),v.disabled=!1,E}catch(w){return console.error("[time-slots-ui] Error loading services:",w),v.innerHTML='<option value="">Error loading services. Please try again.</option>',!1}}function Q(){var k,w,A,x,E;(k=y.grid)==null||k.classList.add("hidden"),(w=y.loading)==null||w.classList.add("hidden"),(A=y.empty)==null||A.classList.add("hidden"),(x=y.error)==null||x.classList.add("hidden"),(E=y.prompt)==null||E.classList.add("hidden")}function oe(k){document.querySelectorAll(".time-slot-btn").forEach(w=>{w.classList.remove("bg-blue-600","text-white","border-blue-600","dark:bg-blue-600","dark:border-blue-600"),w.classList.add("bg-white","dark:bg-gray-700","text-gray-700","dark:text-gray-300","border-gray-300","dark:border-gray-600")}),k.classList.remove("bg-white","dark:bg-gray-700","text-gray-700","dark:text-gray-300","border-gray-300","dark:border-gray-600"),k.classList.add("bg-blue-600","text-white","border-blue-600","dark:bg-blue-600","dark:border-blue-600")}function de(k){k.addEventListener("click",function(){oe(this),b.value=this.dataset.time,typeof p=="function"&&p(this.dataset.time);const w=document.createElement("div");w.className="fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 flex items-center gap-2",w.innerHTML='<span class="material-symbols-outlined text-sm">check_circle</span><span>Time slot selected: '+this.dataset.time+"</span>",document.body.appendChild(w),setTimeout(()=>w.remove(),1500)})}function De(k){y.grid.innerHTML="",y.grid.className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2";const w=b.value||f;let A=!1;if(k.forEach(x=>{const E=document.createElement("button");E.type="button",E.className="time-slot-btn px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 hover:border-blue-500 dark:hover:border-blue-500 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500",E.textContent=Fe(x);const T=Me(x);E.dataset.time=T,E.dataset.startTime=x.start??x.startTime??"",E.dataset.endTime=x.end??x.endTime??"",de(E),w&&T&&w===T&&(A=!0,oe(E),b.value=T),y.grid.appendChild(E)}),f&&!A&&h){const x=document.createElement("button");x.type="button",x.className="time-slot-btn px-3 py-2 text-sm font-medium text-white bg-blue-600 border border-blue-600 rounded-lg hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500",x.textContent=`${f} (current)`,x.dataset.time=f,x.title="Currently scheduled time (keep this time)",de(x),b.value=f,y.grid.prepend(x)}}async function z(k=!1){var T,$,F,O,M,N,q,U,le,ce,me;Q();const w=g.value,A=v.value;let x=S.value;if((T=y.availableDatesHint)==null||T.classList.add("hidden"),($=y.noAvailabilityWarning)==null||$.classList.add("hidden"),!w||!A){(F=y.prompt)==null||F.classList.remove("hidden"),b.value="";return}const E=x||new Date().toISOString().slice(0,10);(O=y.loading)==null||O.classList.remove("hidden");try{let B=await _(w,A,E,k);if(!!x&&B.availableDates.length&&!B.availableDates.includes(x)&&(B=await _(w,A,x,!0)),ke(B.availableDates),!B.availableDates.length){(M=y.loading)==null||M.classList.add("hidden"),(N=y.empty)==null||N.classList.remove("hidden"),b.value="";return}const{date:ue,updated:lt}=J(B,x);ue&&(x=ue);const Se=Ee(B,x);if(De(Se),!y.grid.children.length){(q=y.loading)==null||q.classList.add("hidden"),(U=y.empty)==null||U.classList.remove("hidden"),b.value="";return}(le=y.loading)==null||le.classList.add("hidden"),y.grid.classList.remove("hidden")}catch(B){console.error("[time-slots-ui] Error loading time slots:",B),(ce=y.loading)==null||ce.classList.add("hidden"),(me=y.error)==null||me.classList.remove("hidden"),y.errorMsg&&(y.errorMsg.textContent=B.message||"Failed to load time slots"),b.value=""}}g.addEventListener("change",async()=>{var k;await re(g.value),b.value="",S.value="",Q(),(k=y.prompt)==null||k.classList.remove("hidden"),v.value&&z(!0)}),v.addEventListener("change",()=>{b.value="",S.value="",z(!0)}),S.addEventListener("change",()=>{b.value="",z()}),(async()=>{var k;g.value?(await re(g.value),setTimeout(()=>{v.value&&z(!0)},100)):(Q(),(k=y.prompt)==null||k.classList.remove("hidden"))})()}typeof window<"u"&&(window.initTimeSlotsUI=tt);function nt(){if(!document.querySelector('form[action*="/appointments/store"]'))return;const e=new URLSearchParams(window.location.search),t=e.get("date"),n=e.get("time"),s=e.get("provider_id");if(t){const a=document.getElementById("appointment_date");a&&(a.value=t,a.dispatchEvent(new Event("change",{bubbles:!0})))}if(n){const a=document.getElementById("appointment_time");a&&(a.value=n,a.dispatchEvent(new Event("change",{bubbles:!0})))}if(s){const a=document.getElementById("provider_id");a&&(a.value=s,a.dispatchEvent(new Event("change",{bubbles:!0})))}}function st(){const i=document.querySelector("[data-status-filter-container]");if(!i)return;const e=Array.from(i.querySelectorAll(".status-filter-btn"));if(!e.length)return;const t=document.getElementById("appointments-inline-calendar"),n=l=>{e.forEach(d=>{d.dataset.status===l&&l!==""?(d.classList.add("is-active"),d.setAttribute("aria-pressed","true")):(d.classList.remove("is-active"),d.setAttribute("aria-pressed","false"))}),i.dataset.activeStatus=l,t&&(t.dataset.activeStatus=l)},s=l=>{e.forEach(d=>{l?d.classList.add("is-loading"):d.classList.remove("is-loading")})},a=l=>{const d=new URL(window.location.href);l?d.searchParams.set("status",l):d.searchParams.delete("status"),window.history.replaceState({},"",`${d.pathname}${d.search}`)},r=l=>{if(!t)return Promise.resolve();const d=l||null,c=window.scheduler;return c&&typeof c.setStatusFilter=="function"?c.setStatusFilter(d):new Promise(h=>{const u=f=>{var g;const p=((g=f==null?void 0:f.detail)==null?void 0:g.scheduler)||window.scheduler;p&&typeof p.setStatusFilter=="function"?Promise.resolve(p.setStatusFilter(d)).finally(h):h()};window.addEventListener("scheduler:ready",u,{once:!0})})},o=i.dataset.activeStatus||"";n(o),e.forEach(l=>{l.dataset.statusFilterBound!=="true"&&(l.dataset.statusFilterBound="true",l.addEventListener("click",()=>{const d=l.dataset.status||"",c=i.dataset.activeStatus||"",u=d===c?"":d;n(u),a(u),s(!0),r(u).catch(f=>{console.error("[app.js] Failed to apply scheduler status filter",f)}).finally(()=>{s(!1)}),ve({source:"status-filter",status:u||null})}))})}let j=null;function at(){if(typeof window>"u"||typeof document>"u")return"";const i=document.querySelector("[data-status-filter-container]");if(i){const t=i.dataset.activeStatus;if(typeof t=="string"&&t!=="")return t}const e=document.getElementById("appointments-inline-calendar");if(e){const t=e.dataset.activeStatus;if(typeof t=="string"&&t!=="")return t}return window.scheduler&&typeof window.scheduler.statusFilter<"u"&&window.scheduler.statusFilter!==null?window.scheduler.statusFilter:""}async function K(){if(!(typeof window>"u")){j&&j.abort(),j=new AbortController;try{const i=at(),e=new URL("/api/dashboard/appointment-stats",window.location.origin);i&&e.searchParams.set("status",i);const t=await fetch(e,{method:"GET",headers:{Accept:"application/json"},cache:"no-store",signal:j.signal});if(!t.ok)throw new Error(`Failed to refresh stats: HTTP ${t.status}`);const n=await t.json(),s=n.data||n;ne("upcomingCount",s.upcoming),ne("completedCount",s.completed),ne("pendingCount",s.pending)}catch(i){if(i.name==="AbortError")return;console.error("[app.js] Failed to refresh appointment stats",i)}finally{j=null}}}function ne(i,e){const t=document.getElementById(i);if(!t)return;const n=new Intl.NumberFormat(void 0,{maximumFractionDigits:0}),s=typeof e=="number"?e:parseInt(e??0,10)||0;t.textContent=n.format(s)}function ve(i={}){typeof window>"u"||(K(),window.dispatchEvent(new CustomEvent("appointments-updated",{detail:i})))}function xe(){typeof pe<"u"&&pe.initAllCharts(),it(),st(),Be(),nt()}document.addEventListener("DOMContentLoaded",function(){xe(),K()});document.addEventListener("spa:navigated",function(i){xe(),K()});typeof window<"u"&&(window.refreshAppointmentStats=K,window.emitAppointmentsUpdated=ve);async function it(){const i=document.getElementById("appointments-inline-calendar");if(i)try{window.scheduler&&typeof window.scheduler.destroy=="function"&&(window.scheduler.destroy(),window.scheduler=null);const e=i.dataset.initialDate||new Date().toISOString().split("T")[0],t=i.dataset.activeStatus||"",n=new et("appointments-inline-calendar",{initialView:"month",initialDate:e,timezone:window.appTimezone||"America/New_York",apiBaseUrl:"/api/appointments",statusFilter:t||null,onAppointmentClick:ot});await n.init(),rt(n),window.scheduler=n,window.dispatchEvent(new CustomEvent("scheduler:ready",{detail:{scheduler:n}})),new URLSearchParams(window.location.search).has("refresh")&&(window.history.replaceState({},document.title,window.location.pathname),await n.loadAppointments(),n.render())}catch(e){console.error("❌ Failed to initialize scheduler:",e),i.innerHTML=`
            <div class="flex flex-col items-center justify-center p-12">
                <span class="material-symbols-outlined text-red-500 text-6xl mb-4">error</span>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                    Scheduler Error
                </h3>
                <p class="text-gray-600 dark:text-gray-400 text-center max-w-md">
                    Failed to load scheduler. Please refresh the page.
                </p>
            </div>
        `}}function rt(i){document.querySelectorAll('[data-calendar-action="day"], [data-calendar-action="week"], [data-calendar-action="month"]').forEach(s=>{s.addEventListener("click",async()=>{const a=s.dataset.calendarAction;try{await i.changeView(a),document.querySelectorAll("[data-calendar-action]").forEach(r=>{r.dataset.calendarAction===a?(r.classList.add("bg-blue-600","text-white","shadow-sm"),r.classList.remove("bg-slate-100","dark:bg-slate-700","text-slate-700","dark:text-slate-300")):["day","week","month"].includes(r.dataset.calendarAction)&&(r.classList.remove("bg-blue-600","text-white","shadow-sm"),r.classList.add("bg-slate-100","dark:bg-slate-700","text-slate-700","dark:text-slate-300"))}),V(i)}catch(r){console.error("Failed to change view:",r)}})});const e=document.querySelector('[data-calendar-action="today"]');e&&e.addEventListener("click",async()=>{try{await i.navigateToToday(),V(i)}catch(s){console.error("Failed to navigate to today:",s)}});const t=document.querySelector('[data-calendar-action="prev"]');t&&t.addEventListener("click",async()=>{try{await i.navigatePrev(),V(i)}catch(s){console.error("Failed to navigate to previous:",s)}});const n=document.querySelector('[data-calendar-action="next"]');n&&n.addEventListener("click",async()=>{try{await i.navigateNext(),V(i)}catch(s){console.error("Failed to navigate to next:",s)}}),we(i),V(i)}function V(i){const e=document.getElementById("scheduler-date-display");if(!e)return;const{currentDate:t,currentView:n}=i;let s="";switch(n){case"day":s=t.toFormat("EEEE, MMMM d, yyyy");break;case"week":const a=t.startOf("week"),r=t.endOf("week");s=`${a.toFormat("MMM d")} - ${r.toFormat("MMM d, yyyy")}`;break;case"month":default:s=t.toFormat("MMMM yyyy");break}e.textContent=s}function we(i){const e=document.getElementById("provider-legend");!e||!i.providers||i.providers.length===0||(e.innerHTML=i.providers.map(t=>{const n=t.color||"#3B82F6";return`
            <button type="button" 
                    class="provider-legend-item flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs font-medium transition-all duration-200 ${i.visibleProviders.has(t.id)?"bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white":"bg-gray-50 dark:bg-gray-800 text-gray-400 dark:text-gray-500 opacity-50"} hover:bg-gray-200 dark:hover:bg-gray-600"
                    data-provider-id="${t.id}"
                    title="Toggle ${t.name}">
                <span class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: ${n};"></span>
                <span class="truncate max-w-[120px]">${t.name}</span>
            </button>
        `}).join(""),e.querySelectorAll(".provider-legend-item").forEach(t=>{t.addEventListener("click",()=>{const n=parseInt(t.dataset.providerId);i.toggleProvider(n),we(i)})}))}function ot(i){var e;(e=window.scheduler)!=null&&e.appointmentDetailsModal?window.scheduler.appointmentDetailsModal.open(i):console.error("[app.js] Appointment details modal not available")}
