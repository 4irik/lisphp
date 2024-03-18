(def set-in (lambda (m k v) eval (id eval-in m (id def (symbol (++ "n-" k)) v))))
(def get-in (lambda (m k) eval (id eval-in m (id get (++ "n-" k)))))
(def fib-wrapper (lambda (n)
    (do
        (def m (env-gen))
        (def fib (lambda (n)
        (do
          (def v (get-in m n))
          (cond
            (< n 2) (+ 0.0 n)
            (eq? "Double" (typeof v)) v
            (do
              (def r (+ (fib (- n 1)) (fib (- n 2))))
              (set-in m n r)
              r
            ))))
    (fib n)
    ))
(fib-wrapper 200)
