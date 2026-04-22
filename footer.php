    <script>
        (function(){
            const c=document.getElementById('particle-canvas'),ctx=c.getContext('2d');
            if(!c) return;
            let w,h,p=[],m={x:-1000,y:-1000};
            function resize(){w=c.width=window.innerWidth;h=c.height=window.innerHeight}
            window.addEventListener('resize',resize);resize();
            window.addEventListener('mousemove',e=>{m.x=e.clientX;m.y=e.clientY});
            for(let i=0;i<80;i++)p.push({x:Math.random()*w,y:Math.random()*h,vx:Math.random()*0.1-0.05,vy:Math.random()*0.1-0.05,r:Math.random()*1+0.5,a:Math.random()*0.15+0.05});
            function draw(){
                ctx.clearRect(0,0,w,h);
                p.forEach(i=>{
                    let dx=m.x-i.x,dy=m.y-i.y,d=Math.sqrt(dx*dx+dy*dy);
                    if(d<150){ i.x-=dx*0.005;i.y-=dy*0.005; }
                    i.x+=i.vx;i.y+=i.vy;
                    if(i.x<0)i.x=w;if(i.x>w)i.x=0;if(i.y<0)i.y=h;if(i.y>h)i.y=0;
                    ctx.beginPath();ctx.arc(i.x,i.y,i.r,0,Math.PI*2);
                    ctx.fillStyle=`rgba(255,179,0,${i.a})`;ctx.fill();
                });
                requestAnimationFrame(draw);
            }
            draw();
        })();
    </script>
</body>
</html>
