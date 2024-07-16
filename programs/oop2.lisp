(def ctor (lambda (a)
    (do
  (def inc (lambda (x) (do (set! a (+ a x)) a)))
  (def dec (lambda (x) (do (set! a (- a x)) a)))
  (def get (lambda ()      a))
  (lambda (msg)
    (cond (= "inc" msg) inc
      (cond (= "dec" msg) dec
        (cond (= "get" msg) get "?")))))))